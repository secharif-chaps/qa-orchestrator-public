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
 *   asynchronous post-save pipeline via {@see RunPostSavePipelineAction}.
 *
 * The handler is the single place that owns the save/no-save decision —
 * pipeline processors are pure transformers of the context, never side
 * effectors on persistence.
 */
#[AsMessageHandler]
readonly class IngestDocumentHandler
{
    public function __construct(
        private CollectTaskGatewayInterface $collectTaskGateway,
        private DocumentGatewayInterface $documentGateway,
        private ValidatorInterface $validator,
        private MessageBusInterface $messageBus,
        private PreSaveDocumentPipelineInterface $preSavePipeline,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(IngestDocumentAction $action): Document
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
        }

        $watchFile = $document->getWatchFile();
        if (null !== $watchFile) {
            $context = $this->preSavePipeline->process($document, $watchFile);

            if (null !== $context->duplicateOf) {
                $this->logger?->info('Pre-save pipeline detected duplicate, skipping save', [
                    'document_id' => $document->getId(),
                    'duplicate_of' => $context->duplicateOf,
                    'collect_task_id' => $action->collectTaskId,
                ]);

                return $document;
            }

            // The pipeline operates on the same Document instance, so any
            // enrichments performed by pre-save processors are already
            // reflected on $document by reference.
        }

        $this->documentGateway->save($document);

        $this->messageBus->dispatch(
            new RunPostSavePipelineAction(documentId: $document->getId()),
            [new DispatchAfterCurrentBusStamp()],
        );

        return $document;
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
