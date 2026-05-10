<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect\Web;

use App\Application\Collect\Web\FetchWebUrlAction;
use App\Application\Collect\Web\FetchWebUrlHandler;
use App\Application\Document\IngestDocumentAction;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentBuilderFromHtmlMetadata;
use App\Domain\Document\HtmlFetcherInterface;
use App\Domain\Document\HtmlFetchException;
use App\Domain\Document\HtmlMetadata;
use App\Domain\Document\HtmlMetadataExtractor;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Collect\NullCollectTaskGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(FetchWebUrlHandler::class)]
class FetchWebUrlHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private const string TASK_ID = 'collect-task-web-1';
    private NullCollectTaskGateway $collectTaskGateway;
    private HtmlFetcherInterface&Stub $htmlFetcher;
    private HtmlMetadataExtractor&Stub $metadataExtractor;
    private DocumentBuilderFromHtmlMetadata&Stub $documentBuilder;
    private NullMessageBus $messageBus;
    private EventDispatcherInterface&Stub $eventDispatcher;
    private FetchWebUrlHandler $handler;

    protected function setUp(): void
    {
        $this->collectTaskGateway = new NullCollectTaskGateway();
        $this->htmlFetcher = $this->createStub(HtmlFetcherInterface::class);
        $this->metadataExtractor = $this->createStub(HtmlMetadataExtractor::class);
        $this->documentBuilder = $this->createStub(DocumentBuilderFromHtmlMetadata::class);
        $this->messageBus = new NullMessageBus();
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);

        $this->handler = new FetchWebUrlHandler(
            $this->collectTaskGateway,
            $this->htmlFetcher,
            $this->metadataExtractor,
            $this->documentBuilder,
            $this->messageBus,
            $this->eventDispatcher,
            new \App\Infrastructure\Url\PhpUrlSanitizer(),
            new NullLogger(),
        );
    }

    public function testIngestsFromUrlAndTransitionsCollectTaskToCompleted(): void
    {
        $task = $this->createCollectTask([
            'url' => 'https://example.com/article',
        ]);
        // CreateCollectTaskHandler would have already started the task before
        // dispatching FetchWebUrlAction; mirror that here.
        $task->start('web-test', $this->eventDispatcher);

        $this->htmlFetcher->method('fetch')
->willReturn('<html><body>fetched</body></html>');
        $this->metadataExtractor->method('extract')
->willReturn($this->makeMetadata());
        $this->documentBuilder->method('build')
->willReturn($this->makeDocument('doc-from-url'));

        $this->handler->__invoke(new FetchWebUrlAction(self::TASK_ID));

        self::assertSame(CollectTaskStatus::COMPLETED, $task->getStatus());
        self::assertCount(1, $this->messageBus->getDispatchedMessages());
        $dispatched = $this->messageBus->getDispatchedMessages()[0];
        self::assertInstanceOf(IngestDocumentAction::class, $dispatched);
        self::assertSame(self::TASK_ID, $dispatched->collectTaskId);
        self::assertSame('doc-from-url', $dispatched->document->getId());
    }

    public function testIngestsFromRawHtmlWhenUrlIsAbsent(): void
    {
        $task = $this->createCollectTask([
            'raw_html' => '<html><body>pasted</body></html>',
        ]);
        $task->start('web-test', $this->eventDispatcher);

        $this->htmlFetcher->method('fetch')
->willThrowException(new \LogicException('htmlFetcher must not be called when raw_html is provided'));
        $this->metadataExtractor->method('extract')
->willReturn($this->makeMetadata());
        $this->documentBuilder->method('build')
->willReturn($this->makeDocument('doc-from-paste'));

        $this->handler->__invoke(new FetchWebUrlAction(self::TASK_ID));

        self::assertSame(CollectTaskStatus::COMPLETED, $task->getStatus());
        self::assertTrue($this->messageBus->hasDispatched(IngestDocumentAction::class));
    }

    public function testForwardsSyncStampToIngestDocumentActionWhenSyncIsTrue(): void
    {
        $task = $this->createCollectTask([
            'url' => 'https://example.com/sync',
        ]);
        $task->start('web-test', $this->eventDispatcher);

        $this->htmlFetcher->method('fetch')
->willReturn('<html><body>x</body></html>');
        $this->metadataExtractor->method('extract')
->willReturn($this->makeMetadata());
        $this->documentBuilder->method('build')
->willReturn($this->makeDocument('doc-sync'));

        $stampCapture = null;
        $this->messageBus = new NullMessageBus(function (object $message, array $stamps) use (&$stampCapture): mixed {
            if ($message instanceof IngestDocumentAction) {
                $stampCapture = $stamps;
            }

            return null;
        });

        $handler = new FetchWebUrlHandler(
            $this->collectTaskGateway,
            $this->htmlFetcher,
            $this->metadataExtractor,
            $this->documentBuilder,
            $this->messageBus,
            $this->eventDispatcher,
            new \App\Infrastructure\Url\PhpUrlSanitizer(),
            new NullLogger(),
        );

        $handler->__invoke(new FetchWebUrlAction(self::TASK_ID, sync: true));

        self::assertNotNull($stampCapture);
        $hasSyncStamp = false;
        foreach ($stampCapture as $stamp) {
            if ($stamp instanceof TransportNamesStamp && \in_array('sync', $stamp->getTransportNames(), true)) {
                $hasSyncStamp = true;
                break;
            }
        }
        self::assertTrue($hasSyncStamp, 'IngestDocumentAction must be pinned on the sync transport when sync=true');

        $dispatched = $this->messageBus->getDispatchedMessages()[0];
        self::assertInstanceOf(IngestDocumentAction::class, $dispatched);
        self::assertTrue($dispatched->sync, 'IngestDocumentAction must carry sync=true to forward the chain');
    }

    public function testTransitionsTaskToFailedWithoutThrowingWhenConfigurationLacksUrlAndRawHtml(): void
    {
        // Re-throwing on a business-level failure (bad operator config) would
        // let DoctrineTransactionMiddleware roll back the FAILED transition
        // and leave the source locked. The handler returns silently; the CLI
        // / API reload the task and surface the FAILED status themselves.
        $task = $this->createCollectTask([]);
        $task->start('web-test', $this->eventDispatcher);

        $this->handler->__invoke(new FetchWebUrlAction(self::TASK_ID));

        self::assertSame(CollectTaskStatus::FAILED, $task->getStatus());
        self::assertSame([], $this->messageBus->getDispatchedMessages());
    }

    public function testTransitionsTaskToFailedWithoutThrowingWhenFetchFails(): void
    {
        // Same rationale as the bad-config branch: 4xx upstream failures are
        // deterministic, retrying inflates the error rate without changing
        // the outcome, and re-throwing would erase the FAILED persistence.
        $task = $this->createCollectTask([
            'url' => 'https://example.com/down',
        ]);
        $task->start('web-test', $this->eventDispatcher);

        $this->htmlFetcher->method('fetch')
->willThrowException(HtmlFetchException::fetchFailed('https://example.com/down', 'connection refused'));

        $this->handler->__invoke(new FetchWebUrlAction(self::TASK_ID));

        self::assertSame(CollectTaskStatus::FAILED, $task->getStatus());
        self::assertSame([], $this->messageBus->getDispatchedMessages());
    }

    public function testThrowsWhenCollectTaskNotFound(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessageMatches('/CollectTask not found/');

        $this->handler->__invoke(new FetchWebUrlAction('missing-collect-task'));
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createCollectTask(array $configuration): CollectTask
    {
        $organisation = new Organisation('Test Org', 'org-1');
        $watchFile = new WatchFile('Test Watch', 'objective', $organisation);
        $this->forcePropertyValue($watchFile, 'wf-1');
        $source = new Source(
            name: 'Manual Source',
            description: new TranslatedText('Description', 'Description'),
            type: SourceType::MANUAL,
            url: 'manual://wf-1',
            primaryDomain: 'manual',
            relevance: new TranslatedText('Relevance', 'Relevance'),
            actor: null,
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($source, 'src-1');

        $task = new CollectTask(
            source: $source,
            watchFile: $watchFile,
            providerName: 'web',
            configuration: $configuration,
        );
        $this->forcePropertyValue($task, self::TASK_ID);
        $this->collectTaskGateway->save($task);

        return $task;
    }

    private function makeMetadata(): HtmlMetadata
    {
        return new HtmlMetadata(
            title: 'Test article',
            excerpt: 'Test excerpt long enough to be valid for ingestion downstream.',
            content: '<p>Test article content body, long enough to pass validation rules.</p>',
            language: 'en',
            datePublish: new \DateTimeImmutable(),
            imageUrl: null,
            canonicalUrl: null,
            author: null,
            siteName: 'example.com',
        );
    }

    private function makeDocument(string $id): Document
    {
        $document = new Document(
            id: $id,
            title: 'Test article',
            excerpt: 'Test excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content',
        );

        return $document;
    }
}
