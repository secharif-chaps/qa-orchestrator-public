<?php

declare(strict_types=1);

namespace App\Tests\Units\UserInterface\Command\Document;

use App\Application\Document\IngestDocumentAction;
use App\Application\Document\IngestDocumentHandler;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentBuilderFromHtmlMetadata;
use App\Domain\Document\HtmlFetcherInterface;
use App\Domain\Document\HtmlFetchException;
use App\Domain\Document\HtmlMetadata;
use App\Domain\Document\HtmlMetadataExtractor;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\ManualSourceFactory;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Tests\Utils\EntityUtilsTrait;
use App\UserInterface\Command\Document\CreateDocumentCommand;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
class CreateDocumentCommandTest extends TestCase
{
    use EntityUtilsTrait;
    private MessageBusInterface&MockObject $messageBus;
    private IngestDocumentHandler&MockObject $ingestDocumentHandler;
    private WatchFileGatewayInterface&MockObject $watchFileGateway;
    private SourceGatewayInterface&MockObject $sourceGateway;
    private CollectTaskGatewayInterface&MockObject $collectTaskGateway;
    private HtmlFetcherInterface&MockObject $htmlFetcher;
    private HtmlMetadataExtractor&Stub $metadataExtractor;
    private DocumentBuilderFromHtmlMetadata $documentBuilder;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->messageBus->method('dispatch')
            ->willReturnCallback(static fn ($message) => new Envelope($message));

        $this->ingestDocumentHandler = $this->createMock(IngestDocumentHandler::class);
        $this->watchFileGateway = $this->createMock(WatchFileGatewayInterface::class);
        $this->sourceGateway = $this->createMock(SourceGatewayInterface::class);
        $this->collectTaskGateway = $this->createMock(CollectTaskGatewayInterface::class);
        // Real Doctrine gateway assigns an id on save via lifecycle callbacks; mimic that here.
        $this->collectTaskGateway
            ->method('save')
            ->willReturnCallback(function ($collectTask): void {
                $this->forcePropertyValue($collectTask, 'collect-task-' . bin2hex(random_bytes(4)));
            });
        $this->htmlFetcher = $this->createMock(HtmlFetcherInterface::class);
        $this->metadataExtractor = $this->createStub(HtmlMetadataExtractor::class);
        $this->documentBuilder = new DocumentBuilderFromHtmlMetadata();

        $command = new CreateDocumentCommand(
            messageBus: $this->messageBus,
            ingestDocumentHandler: $this->ingestDocumentHandler,
            watchFileGateway: $this->watchFileGateway,
            sourceGateway: $this->sourceGateway,
            collectTaskGateway: $this->collectTaskGateway,
            htmlFetcher: $this->htmlFetcher,
            metadataExtractor: $this->metadataExtractor,
            documentBuilder: $this->documentBuilder,
            manualSourceFactory: new ManualSourceFactory(),
            eventDispatcher: $this->createStub(EventDispatcherInterface::class),
        );

        $this->commandTester = new CommandTester($command);
    }

    public function testFailsWhenNeitherUrlNorHtmlFileProvided(): void
    {
        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString(
            'You must provide either --url or --html-file',
            $this->commandTester->getDisplay()
        );
    }

    public function testFailsWhenWatchFileNotFound(): void
    {
        $this->watchFileGateway->method('get')
            ->willThrowException(new WatchFileNotFoundException('WatchFile "missing-wf" not found'));

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'missing-wf',
            '--url' => 'https://example.com/article',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('not found', $this->commandTester->getDisplay());
    }

    public function testFetchUrlAndDispatchAction(): void
    {
        $watchFile = $this->makeWatchFileWithManualSource();
        $this->watchFileGateway->method('get')
            ->willReturn($watchFile);

        $this->htmlFetcher
            ->expects($this->once())
            ->method('fetch')
            ->with('https://example.com/article')
            ->willReturn('<html><body>article body</body></html>');

        $this->metadataExtractor->method('extract')
            ->willReturn($this->makeMetadata(title: 'Fetched article'));

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(IngestDocumentAction::class))
            ->willReturnCallback(static fn ($message) => new Envelope($message));

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--url' => 'https://example.com/article',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString(
            'Document dispatched for creation: Fetched article',
            $this->commandTester->getDisplay()
        );
    }

    public function testFailsWhenUrlFetchThrowsHtmlFetchException(): void
    {
        $this->watchFileGateway->method('get')
            ->willReturn($this->makeWatchFileWithManualSource());

        $this->htmlFetcher
            ->method('fetch')
            ->willThrowException(HtmlFetchException::fetchFailed('https://example.com/x', 'connection refused'));

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--url' => 'https://example.com/x',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Failed to fetch URL', $this->commandTester->getDisplay());
    }

    public function testFailsWhenHtmlFileDoesNotExist(): void
    {
        $this->watchFileGateway->method('get')
            ->willReturn($this->makeWatchFileWithManualSource());

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--html-file' => '/tmp/this-file-does-not-exist-' . bin2hex(random_bytes(8)),
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('File not found', $this->commandTester->getDisplay());
    }

    public function testReadsLocalHtmlFileAndDispatches(): void
    {
        $watchFile = $this->makeWatchFileWithManualSource();
        $this->watchFileGateway->method('get')
            ->willReturn($watchFile);

        $this->metadataExtractor->method('extract')
            ->willReturn($this->makeMetadata(title: 'From local file'));

        $tmpFile = tempnam(sys_get_temp_dir(), 'create-doc-test-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, '<html><body>local content</body></html>');

        try {
            $this->messageBus
                ->expects($this->once())
                ->method('dispatch')
                ->willReturnCallback(static fn ($message) => new Envelope($message));

            $exitCode = $this->commandTester->execute([
                'watchFileId' => 'wf-1',
                '--html-file' => $tmpFile,
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString(
                'Document dispatched for creation: From local file',
                $this->commandTester->getDisplay()
            );
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testFailsWhenContentHasIssue(): void
    {
        $this->watchFileGateway->method('get')
            ->willReturn($this->makeWatchFileWithManualSource());

        $this->htmlFetcher->method('fetch')
            ->willReturn('<html><title>403 Forbidden</title></html>');

        // A title that triggers HtmlMetadata::getContentIssue() (matches ERROR_TITLE_PATTERNS).
        $this->metadataExtractor->method('extract')
            ->willReturn($this->makeMetadata(title: '403 Forbidden', content: ''));

        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--url' => 'https://example.com/blocked',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Content rejected', $this->commandTester->getDisplay());
    }

    public function testSyncOptionInvokesHandlerDirectlyWithoutDispatching(): void
    {
        $watchFile = $this->makeWatchFileWithManualSource();
        $this->watchFileGateway->method('get')
            ->willReturn($watchFile);

        $this->htmlFetcher->method('fetch')
            ->willReturn('<html><body>article body</body></html>');

        $this->metadataExtractor->method('extract')
            ->willReturn($this->makeMetadata(title: 'Sync article'));

        $invokedDocument = $this->makeDocument('doc-sync-1', 'Sync article');
        $this->ingestDocumentHandler
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn($invokedDocument);

        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--url' => 'https://example.com/article',
            '--sync' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Document created: Sync article', $this->commandTester->getDisplay());
    }

    public function testUsesProvidedSourceIdInsteadOfManualSource(): void
    {
        $watchFile = $this->makeWatchFileWithManualSource();
        $this->watchFileGateway->method('get')
            ->willReturn($watchFile);

        $explicitSource = $this->makeManualSource($watchFile, name: 'Pinned source', id: 'src-explicit');
        $this->sourceGateway
            ->expects($this->once())
            ->method('get')
            ->with('src-explicit')
            ->willReturn($explicitSource);

        $this->htmlFetcher->method('fetch')
            ->willReturn('<html><body>article body</body></html>');
        $this->metadataExtractor->method('extract')
            ->willReturn($this->makeMetadata());

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static fn ($message) => new Envelope($message));

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--url' => 'https://example.com/article',
            '--source-id' => 'src-explicit',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Using source: Pinned source', $this->commandTester->getDisplay());
    }

    public function testCreatesNewManualSourceWhenWatchFileHasNone(): void
    {
        $watchFile = $this->makeWatchFile(); // no manual source attached
        $this->watchFileGateway->method('get')
            ->willReturn($watchFile);

        $this->sourceGateway
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static fn (Source $s): bool => SourceType::MANUAL === $s->getType()));

        $this->htmlFetcher->method('fetch')
            ->willReturn('<html><body>article body</body></html>');
        $this->metadataExtractor->method('extract')
            ->willReturn($this->makeMetadata());

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--url' => 'https://example.com/article',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Created new manual source', $this->commandTester->getDisplay());
    }

    public function testTitleAndExcerptOverridesArePropagatedToDispatchedDocument(): void
    {
        $watchFile = $this->makeWatchFileWithManualSource();
        $this->watchFileGateway->method('get')
            ->willReturn($watchFile);

        $this->htmlFetcher->method('fetch')
            ->willReturn('<html><body>article body</body></html>');
        $this->metadataExtractor->method('extract')
            ->willReturn($this->makeMetadata(title: 'Default', excerpt: 'Default excerpt'));

        $captured = null;
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function ($message) use (&$captured): Envelope {
                $captured = $message;

                return new Envelope($message);
            });

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--url' => 'https://example.com/article',
            '--title' => 'CLI Title',
            '--excerpt' => 'CLI Excerpt',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertInstanceOf(IngestDocumentAction::class, $captured);
        self::assertSame('CLI Title', $captured->document->getTitle());
        self::assertSame('CLI Excerpt', $captured->document->getExcerpt());
    }

    private function makeWatchFile(string $id = 'wf-1', string $name = 'Test WatchFile'): WatchFile
    {
        $organisation = new Organisation('Test Org', 'test-org-id');
        $watchFile = new WatchFile($name, 'objective', $organisation);
        $this->forcePropertyValue($watchFile, $id);

        return $watchFile;
    }

    private function makeWatchFileWithManualSource(): WatchFile
    {
        $watchFile = $this->makeWatchFile();
        $watchFile->addSource($this->makeManualSource($watchFile, name: 'Existing Manual', id: 'src-existing'));

        return $watchFile;
    }

    private function makeManualSource(WatchFile $watchFile, string $name, string $id): Source
    {
        $source = new Source(
            name: $name,
            description: new TranslatedText('Source manuelle', 'Manual source'),
            type: SourceType::MANUAL,
            url: 'manual://' . $watchFile->getId(),
            primaryDomain: 'manual',
            relevance: new TranslatedText('Ajout manuel', 'Manual add'),
            actor: null,
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($source, $id);

        return $source;
    }

    private function makeMetadata(
        string $title = 'Test article',
        string $excerpt = 'Test article excerpt that is long enough to pass validation',
        string $content = '<p>Test article content body, long enough to pass the minimum length check on quality validation.</p>',
        string $language = 'en',
    ): HtmlMetadata {
        return new HtmlMetadata(
            title: $title,
            excerpt: $excerpt,
            content: $content,
            language: $language,
            datePublish: new \DateTimeImmutable(),
            imageUrl: null,
            canonicalUrl: null,
            author: null,
            siteName: 'example.com',
        );
    }

    private function makeDocument(string $id, string $title): Document
    {
        $document = new Document(
            id: $id,
            title: $title,
            excerpt: 'Excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: '<p>Body</p>',
            cfcRestricted: false,
        );

        return $document;
    }
}
