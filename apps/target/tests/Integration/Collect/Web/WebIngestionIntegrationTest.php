<?php

declare(strict_types=1);

namespace App\Tests\Integration\Collect\Web;

use App\Application\Collect\Task\CreateCollectTaskAction;
use App\Application\Collect\Web\WebCollectConfig;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\HtmlFetcherInterface;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\Url\UrlSanitizerInterface;
use App\Domain\WatchFile\WatchFileStatus;
use App\Tests\Integration\AbstractApiTestCase;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

/**
 * End-to-end coverage of the provider-agnostic web ingestion chain.
 *
 * Boots the real kernel + DB (`ResetDatabase`); the document store is
 * swapped for an in-memory {@see NullDocumentGateway} (mirrors the
 * pattern in {@see App\Tests\Integration\Document\Pipeline\RunPostSavePipelineHandlerTest})
 * so the post-save pipeline reload doesn't lose the WatchFile relation
 * via the OpenSearch round-trip — the goal here is the **chain wiring**
 * (CreateCollectTaskAction → WebProviderGateway → FetchWebUrlAction →
 * IngestDocumentAction → ContentQualityProcessor → save), not the
 * OpenSearch persistence which is covered separately by
 * {@see App\Tests\Units\Infrastructure\Document\DocumentOpenSearchGatewayRefreshTest}.
 *
 * The HTML fetcher is overridden with a stub returning canned payloads,
 * and the URL sanitizer is replaced with a no-op so `example.com` URLs
 * don't need DNS resolution.
 */
class WebIngestionIntegrationTest extends AbstractApiTestCase
{
    use Factories;
    use InteractsWithMessenger;
    use ResetDatabase;
    private MessageBusInterface $bus;
    private NullDocumentGateway $documentGateway;
    private CollectTaskGatewayInterface $collectTaskGateway;
    private FakeHtmlFetcher $htmlFetcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->htmlFetcher = new FakeHtmlFetcher();
        self::getContainer()->set(HtmlFetcherInterface::class, $this->htmlFetcher);
        self::getContainer()->set(UrlSanitizerInterface::class, new AlwaysSafeUrlSanitizer());

        $this->documentGateway = new NullDocumentGateway();
        self::getContainer()->set(DocumentGatewayInterface::class, $this->documentGateway);

        /** @var MessageBusInterface $bus */
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $this->bus = $bus;
        /** @var CollectTaskGatewayInterface $collectTaskGateway */
        $collectTaskGateway = self::getContainer()->get(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGateway;
    }

    #[Test]
    public function syncChainPersistsDocumentLinkedToCollectTask(): void
    {
        $watchFile = $this->makeWatchFile();
        $source = $this->makeManualSource($watchFile);

        $url = 'https://example.com/article';
        $this->htmlFetcher->reply(
            url: $url,
            html: <<<'HTML'
                <html><head><title>Provider-agnostic ingestion lands</title></head>
                <body>
                <article>
                <h1>Provider-agnostic ingestion lands</h1>
                <p>End-to-end web ingestion now goes through CreateCollectTaskAction
                before the IngestDocumentHandler, so every entry point reuses the
                same orchestrator regardless of where the URL came from.</p>
                </article>
                </body></html>
                HTML
            ,
        );

        $config = new WebCollectConfig(url: $url, syncChain: true);
        $envelope = $this->bus->dispatch(
            new CreateCollectTaskAction(
                sourceId: $source->getId(),
                watchFileId: $watchFile->getId(),
                start: true,
                configuration: $config->toCollectTaskConfiguration(),
            ),
            [new TransportNamesStamp(['sync'])],
        );

        $collectTask = $this->extractCollectTask($envelope);
        $collectTaskId = $collectTask->getId();
        self::assertIsString($collectTaskId);

        // Reload via the gateway to observe transitions made by the deferred
        // FetchWebUrlHandler (CREATED → QUEUED via CreateCollectTaskHandler,
        // then RUNNING → COMPLETED via FetchWebUrlHandler).
        $reloaded = $this->collectTaskGateway->get($collectTaskId);
        self::assertSame(CollectTaskStatus::COMPLETED, $reloaded->getStatus());

        $documents = $this->documentGateway->findByCollectTaskId($collectTaskId);
        self::assertCount(1, $documents);
        self::assertSame($collectTaskId, $documents[0]->getCollectTaskId());
        self::assertSame('Provider-agnostic ingestion lands', $documents[0]->getTitle());
        self::assertSame($watchFile->getId(), $documents[0]->getWatchFile()?->getId());
    }

    #[Test]
    public function contentQualityHaltsPipelineWhenFetchedTitleSignalsErrorPage(): void
    {
        $watchFile = $this->makeWatchFile();
        $source = $this->makeManualSource($watchFile);

        $url = 'https://example.com/forbidden';
        $this->htmlFetcher->reply(
            url: $url,
            html: <<<'HTML'
                <html><head><title>403 Forbidden</title></head>
                <body><p>You are not allowed to access this page on this server.</p></body></html>
                HTML
            ,
        );

        $config = new WebCollectConfig(url: $url, syncChain: true);
        $envelope = $this->bus->dispatch(
            new CreateCollectTaskAction(
                sourceId: $source->getId(),
                watchFileId: $watchFile->getId(),
                start: true,
                configuration: $config->toCollectTaskConfiguration(),
            ),
            [new TransportNamesStamp(['sync'])],
        );

        $collectTask = $this->extractCollectTask($envelope);
        $collectTaskId = $collectTask->getId();
        self::assertIsString($collectTaskId);

        // FetchWebUrlHandler completes the task even when the pipeline halts —
        // the fetch + build itself succeeded, only the ingestion was aborted.
        $reloaded = $this->collectTaskGateway->get($collectTaskId);
        self::assertSame(CollectTaskStatus::COMPLETED, $reloaded->getStatus());

        // Pipeline halted on the "403 Forbidden" title → IngestDocumentHandler
        // returned early without saving (TAR-1147 lot 6). No document should
        // be persisted under this collect task.
        self::assertSame([], $this->documentGateway->findByCollectTaskId($collectTaskId));
    }

    #[Test]
    public function rawHtmlPayloadFollowsTheSamePipelineWithoutHttpFetch(): void
    {
        $watchFile = $this->makeWatchFile();
        $source = $this->makeManualSource($watchFile);

        $config = new WebCollectConfig(
            rawHtml: <<<'HTML'
                <html><head><title>Pasted clipping</title></head>
                <body><article>
                <p>Operator pasted this content into the CLI directly. The web
                provider routes it through the same fetcher-skipping path as
                the CLI --html-file option.</p>
                </article></body></html>
                HTML
            ,
            syncChain: true,
        );

        $envelope = $this->bus->dispatch(
            new CreateCollectTaskAction(
                sourceId: $source->getId(),
                watchFileId: $watchFile->getId(),
                start: true,
                configuration: $config->toCollectTaskConfiguration(),
            ),
            [new TransportNamesStamp(['sync'])],
        );

        $collectTask = $this->extractCollectTask($envelope);
        $collectTaskId = $collectTask->getId();
        self::assertIsString($collectTaskId);

        $documents = $this->documentGateway->findByCollectTaskId($collectTaskId);
        self::assertCount(1, $documents);
        self::assertSame('Pasted clipping', $documents[0]->getTitle());
    }

    private function extractCollectTask(\Symfony\Component\Messenger\Envelope $envelope): CollectTask
    {
        $stamp = $envelope->last(HandledStamp::class);
        self::assertInstanceOf(HandledStamp::class, $stamp);
        $result = $stamp->getResult();
        self::assertInstanceOf(CollectTask::class, $result);

        return $result;
    }

    private function makeWatchFile(): \App\Domain\WatchFile\WatchFile
    {
        $user = UserFactory::createOne();

        return WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create([
                'name' => 'Web ingestion test wf',
                'status' => WatchFileStatus::ENABLED,
            ]);
    }

    private function makeManualSource(\App\Domain\WatchFile\WatchFile $watchFile): \App\Domain\Source\Source
    {
        return SourceFactory::new()->create([
            'watchFile' => $watchFile,
            'name' => 'Manual source',
            'type' => SourceType::MANUAL,
            'status' => SourceStatus::ACTIVE,
        ]);
    }
}

/**
 * Test stub mapping URL → HTML so each test declares what
 * {@see HtmlFetcherInterface::fetch()} returns without going through a
 * real Cloudflare client.
 */
class FakeHtmlFetcher implements HtmlFetcherInterface
{
    /** @var array<string, string> */
    private array $replies = [];

    public function reply(string $url, string $html): void
    {
        $this->replies[$url] = $html;
    }

    public function fetch(string $url): string
    {
        if (!isset($this->replies[$url])) {
            throw new \RuntimeException(\sprintf('FakeHtmlFetcher has no reply for "%s".', $url));
        }

        return $this->replies[$url];
    }
}

/**
 * Test stub allowing every URL through. Bypasses the SSRF resolution check
 * so fixtures using `example.com`-style hostnames don't need outbound DNS
 * at test time.
 */
class AlwaysSafeUrlSanitizer implements UrlSanitizerInterface
{
    public function assertSafePublicUrl(string $url): void
    {
        // No-op: tests are not subject to SSRF checks.
    }

    public function redactCredentials(string $url): string
    {
        return $url;
    }
}
