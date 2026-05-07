<?php

declare(strict_types=1);

namespace App\Application\Collect\Web;

use App\Application\Collect\Web\Exception\InvalidWebCollectConfigException;
use App\Application\Document\IngestDocumentAction;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\Exception\CollectTaskNotFoundException;
use App\Domain\Document\DocumentBuilderFromHtmlMetadata;
use App\Domain\Document\HtmlFetcherInterface;
use App\Domain\Document\HtmlFetchException;
use App\Domain\Document\HtmlMetadataExtractor;
use App\Domain\Url\UrlSanitizerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Inline web ingestion handler — single point of HTML parsing/normalisation
 * for the `web` and `manual` providers.
 *
 * Reads the CollectTask's `configuration` to find either a `url` (cloudflare
 * fetch via {@see HtmlFetcherInterface}) or a pasted `raw_html` payload, runs
 * {@see HtmlMetadataExtractor} + {@see DocumentBuilderFromHtmlMetadata} (the
 * exact same path the legacy CLI/API entrypoints used inline), then
 * dispatches the resulting Document through {@see IngestDocumentAction} so
 * pre-save dedup, persistence, and the post-save pipeline stay unchanged.
 *
 * The CollectTask is resumed (QUEUED → RUNNING) at the start and completed
 * (RUNNING → COMPLETED) on success — failures call `fail()` then re-throw so
 * the messenger retry strategy sees them.
 *
 * Quality gating (error pages, paywalls, captchas) is **not** done here — it
 * lives in the pre-save pipeline as a provider-agnostic processor so Apify
 * and Bakus documents get the same treatment.
 */
#[AsMessageHandler]
readonly class FetchWebUrlHandler
{
    private const string SYNC_TRANSPORT = 'sync';

    public function __construct(
        private CollectTaskGatewayInterface $collectTaskGateway,
        private HtmlFetcherInterface $htmlFetcher,
        private HtmlMetadataExtractor $metadataExtractor,
        private DocumentBuilderFromHtmlMetadata $documentBuilder,
        private MessageBusInterface $messageBus,
        private EventDispatcherInterface $eventDispatcher,
        private UrlSanitizerInterface $urlSanitizer,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(FetchWebUrlAction $action): void
    {
        try {
            $collectTask = $this->collectTaskGateway->get($action->collectTaskId);
        } catch (CollectTaskNotFoundException $e) {
            $this->logger?->error('FetchWebUrlHandler: CollectTask not found', [
                'collect_task_id' => $action->collectTaskId,
                'error' => $e->getMessage(),
            ]);

            throw new CollectException(\sprintf('CollectTask not found: %s', $action->collectTaskId), 0, $e);
        }

        try {
            $config = WebCollectConfig::fromCollectTaskConfiguration($collectTask->getConfiguration());
            $config->validate();
        } catch (InvalidWebCollectConfigException $e) {
            $this->logger?->error('FetchWebUrlHandler: invalid web configuration', [
                'collect_task_id' => $action->collectTaskId,
                'error' => $e->getMessage(),
            ]);
            $collectTask->fail($this->eventDispatcher);
            $this->collectTaskGateway->save($collectTask);

            throw new CollectException($e->getMessage(), 0, $e);
        }

        $collectTask->resume($this->eventDispatcher);

        try {
            $html = null !== $config->url ? $this->htmlFetcher->fetch($config->url) : (string) $config->rawHtml;
        } catch (HtmlFetchException $e) {
            // Reachable only when fetching a URL — the raw_html branch
            // never throws HtmlFetchException — so $config->url is non-null.
            $this->logger?->error('FetchWebUrlHandler: failed to fetch URL', [
                'collect_task_id' => $action->collectTaskId,
                'url' => $this->urlSanitizer->redactCredentials((string) $config->url),
                'error' => $e->getMessage(),
            ]);
            $collectTask->fail($this->eventDispatcher);
            $this->collectTaskGateway->save($collectTask);

            throw new CollectException(\sprintf('Failed to fetch URL: %s', $e->getMessage()), 0, $e);
        }

        $metadata = $this->metadataExtractor->extract($html, $config->url);
        $document = $this->documentBuilder->build(
            metadata: $metadata,
            rawHtml: $html,
            sourceUrl: $config->url,
            titleOverride: $config->titleOverride,
            excerptOverride: $config->excerptOverride,
        );

        $collectTaskId = $collectTask->getId();
        \assert(\is_string($collectTaskId));

        // Forward the sync chain when the action carries `sync = true` (CLI
        // `--sync`): pin IngestDocumentAction onto the in-memory `sync`
        // transport so the post-save pipeline runs inline. The async API path
        // leaves `sync = false` so RunPostSavePipelineAction keeps its
        // dedicated `quality_processing` transport routing.
        $stamps = $action->sync ? [new TransportNamesStamp([self::SYNC_TRANSPORT])] : [];
        $this->messageBus->dispatch(
            new IngestDocumentAction(collectTaskId: $collectTaskId, document: $document, sync: $action->sync),
            $stamps,
        );

        $collectTask->complete($this->eventDispatcher);
        $this->collectTaskGateway->save($collectTask);

        $this->logger?->info('FetchWebUrlHandler: web ingestion completed', [
            'collect_task_id' => $action->collectTaskId,
            'document_id' => $document->getId(),
            'has_url' => null !== $config->url,
            'has_raw_html' => null !== $config->rawHtml,
            'sync_chain' => $action->sync,
        ]);
    }
}
