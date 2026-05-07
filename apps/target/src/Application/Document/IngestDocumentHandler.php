<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Application\Document\Pipeline\RunPostSavePipelineAction;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Exception\ValidationException;
use App\Domain\Document\HtmlMetadata;
use App\Domain\Document\Pipeline\PreSaveDocumentPipelineInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Synchronous unified ingestion entrypoint for documents arriving from any
 * source (Apify webhook, Bakus stream, manual API creation, CLI command).
 *
 * The handler runs the **pre-save pipeline** (enrichment + exact-match
 * deduplication) on the incoming document, then either:
 *
 * - skips the save when an exact duplicate is detected (`duplicateOf` set
 *   on the resulting context), or
 * - persists the (potentially enriched) document and dispatches the
 *   post-save pipeline via {@see RunPostSavePipelineAction}. Transport
 *   routing depends on {@see IngestDocumentAction::$sync}: routed to the
 *   default async `quality_processing` transport in production, or
 *   forced onto the `sync` transport via `TransportNamesStamp` when the
 *   caller wants the full pipeline to complete before the ingestion
 *   returns (CLI `--sync`, integration tests).
 *
 * The handler is the single place that owns the save/no-save decision —
 * pipeline processors are pure transformers of the context, never side
 * effectors on persistence.
 */
#[AsMessageHandler]
readonly class IngestDocumentHandler
{
    /**
     * Transport name forced when {@see IngestDocumentAction::$sync} is
     * `true`. Must match the `sync` entry under
     * `framework.messenger.transports` (see `config/packages/messenger.yaml`).
     */
    private const string SYNC_TRANSPORT = 'sync';

    public function __construct(
        private CollectTaskGatewayInterface $collectTaskGateway,
        private DocumentGatewayInterface $documentGateway,
        private ValidatorInterface $validator,
        private MessageBusInterface $messageBus,
        private PreSaveDocumentPipelineInterface $preSavePipeline,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(IngestDocumentAction $action): IngestDocumentResult
    {
        $violations = $this->validator->validate($action->document);
        if (\count($violations) > 0) {
            throw ValidationException::validationFailed(
                'Document validation failed: ' . (string) $violations,
                new ValidationFailedException($action->document, $violations)
            );
        }

        $collectTask = $this->collectTaskGateway->get($action->collectTaskId);

        $document = $action->document;
        $document->capitalizeFrom($collectTask);

        // Provider-id deduplication: when raw_result and document_refined_result
        // produce the same document id, merge the freshly-arrived data into the
        // already-indexed entity so downstream callers always see a single doc.
        $existingDocument = null;
        if (null !== $document->getProviderId()) {
            $existingDocument = $this->documentGateway->findByProviderId($document->getProviderId());
        }

        if (null !== $existingDocument) {
            $this->mergeDocumentData($existingDocument, $document);
            $document = $existingDocument;
            $this->logger?->info('Document merged into existing entry by providerId', [
                'document_id' => $document->getId(),
                'provider_id' => $document->getProviderId(),
                'collect_task_id' => $action->collectTaskId,
            ]);
        }

        $context = null;
        $watchFile = $document->getWatchFile();
        if (null !== $watchFile) {
            $context = $this->preSavePipeline->process(
                document: $document,
                watchFile: $watchFile,
                collectTaskId: $collectTask->getId(),
                provider: $collectTask->getProviderName(),
            );

            if (null !== $context->duplicateOf) {
                $this->logger?->info('Pre-save pipeline detected duplicate, skipping save', [
                    'document_id' => $document->getId(),
                    'duplicate_of' => $context->duplicateOf,
                    'collect_task_id' => $action->collectTaskId,
                ]);

                return new IngestDocumentResult(document: $document, preSaveContext: $context);
            }

            if ($context->isHalted) {
                // Non-duplicate halt — content quality gate, future
                // additional gates. Skip the save (and the post-save
                // pipeline) since the document was rejected.
                $this->logger?->info('Pre-save pipeline halted, skipping save', [
                    'document_id' => $document->getId(),
                    'halt_reason' => $context->haltReason?->en,
                    'collect_task_id' => $action->collectTaskId,
                ]);

                return new IngestDocumentResult(document: $document, preSaveContext: $context);
            }

            // The pipeline operates on the same Document instance, so any
            // enrichments performed by pre-save processors are already
            // reflected on $document by reference.
        }

        // Wait for the document to become searchable when the caller is
        // running the chain inline (CLI `--sync`, sync API path) — they
        // call `findByCollectTaskId` immediately after dispatch and would
        // otherwise hit the OpenSearch refresh interval window.
        $this->documentGateway->save($document, waitForRefresh: $action->sync);

        $stamps = $action->sync
            // Force the `sync` in-memory transport for this dispatch only —
            // overrides the default `quality_processing` async routing
            // declared in `config/packages/messenger.yaml`. The handler
            // runs inline before `dispatch()` returns, which is exactly
            // what the `--sync` caller expects (CLI, integration tests).
            // `DispatchAfterCurrentBusStamp` is unnecessary on the sync
            // path since there's no enclosing bus transaction to defer
            // beyond — the save above is already committed.
            ? [new TransportNamesStamp([self::SYNC_TRANSPORT])]
            : [new DispatchAfterCurrentBusStamp()];

        $this->messageBus->dispatch(new RunPostSavePipelineAction(documentId: $document->getId()), $stamps);

        if ($action->sync) {
            // Reload from the gateway to expose any state the post-save
            // pipeline persisted (today the scoring processors are pure
            // signal collectors that don't mutate the document, but
            // future processors — e.g. fuzzy dedup writing back
            // `duplicates`, or any `*Processor` that calls
            // `documentGateway->save()` — will, and the `--sync` caller
            // expects to observe a coherent post-pipeline document).
            $document = $this->documentGateway->get($document->getId());
        }

        return new IngestDocumentResult(document: $document, preSaveContext: $context);
    }

    /**
     * Merge data from new document into existing document.
     * Refined data (from document_refined_result) takes priority over raw data.
     * Placeholder values ('Untitled Document') never overwrite real data.
     */
    private function mergeDocumentData(Document $existing, Document $new): void
    {
        $newTitle = $new->getTitle();
        if ('' !== $newTitle && HtmlMetadata::UNTITLED !== $newTitle && $newTitle !== $existing->getTitle()) {
            $existing->setTitle($newTitle);
        }

        $newExcerpt = $new->getExcerpt();
        $existingExcerpt = $existing->getExcerpt();
        if (
            '' !== $newExcerpt
            && $newExcerpt !== $existingExcerpt
            && (
                '' === $existingExcerpt
                || \strlen($newExcerpt) > \strlen($existingExcerpt)
            )
        ) {
            $existing->setExcerpt($newExcerpt);
        }

        if ('' !== $new->getContent() && $new->getContent() !== $existing->getContent()) {
            if (\strlen($new->getContent()) > \strlen($existing->getContent())) {
                $existing->setContent($new->getContent());
            }
        }

        if ($new->getType() !== $existing->getType()) {
            $existing->setType($new->getType());
        }

        if ($new->getDatePublish() !== $existing->getDatePublish()) {
            $existing->setDatePublish($new->getDatePublish());
        }

        if (null === $existing->getUrl() && null !== $new->getUrl()) {
            $existing->setUrl($new->getUrl());
        }

        if ($new->isCfcRestricted() !== $existing->isCfcRestricted()) {
            $existing->setCfcRestricted($new->isCfcRestricted());
        }

        $existing->setUpdatedAt(new \DateTimeImmutable());
    }
}
