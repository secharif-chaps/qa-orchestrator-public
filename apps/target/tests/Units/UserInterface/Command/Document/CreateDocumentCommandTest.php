<?php

declare(strict_types=1);

namespace App\Tests\Units\UserInterface\Command\Document;

use App\Application\Collect\Task\CreateCollectTaskAction;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Url\UrlSourceTypeClassifierInterface;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\DocumentQuality\Exception\QualityReportNotFoundException;
use App\Domain\DocumentQuality\QualityReportGatewayInterface;
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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

#[AllowMockObjectsWithoutExpectations]
class CreateDocumentCommandTest extends TestCase
{
    use EntityUtilsTrait;
    private MessageBusInterface&MockObject $messageBus;
    private WatchFileGatewayInterface&MockObject $watchFileGateway;
    private SourceGatewayInterface&MockObject $sourceGateway;
    private CollectTaskGatewayInterface&MockObject $collectTaskGateway;
    private DocumentGatewayInterface&MockObject $documentGateway;
    private QualityReportGatewayInterface&MockObject $qualityReportGateway;
    private UrlSourceTypeClassifierInterface&Stub $urlClassifier;
    private CreateDocumentCommand $command;
    private CommandTester $commandTester;

    /** @var \Closure(object, array<int, mixed>): Envelope */
    private \Closure $dispatchHandler;

    protected function setUp(): void
    {
        // Mutable bus dispatch handler — tests rebind `$this->dispatchHandler`
        // when they need to inject a HandledStamp on the envelope. The mock
        // itself proxies to whichever closure is bound at call time, so we
        // can avoid the PHPUnit method-double ordering trap on the
        // `dispatch()` matcher.
        $this->dispatchHandler = static fn (object $message, array $stamps = []): Envelope => new Envelope($message);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->messageBus
            ->method('dispatch')
            ->willReturnCallback(
                fn (object $message, array $stamps = []): Envelope => ($this->dispatchHandler)($message, $stamps),
            );
        $this->watchFileGateway = $this->createMock(WatchFileGatewayInterface::class);
        $this->sourceGateway = $this->createMock(SourceGatewayInterface::class);
        // The auto-created MANUAL source assigned through ManualSourceFactory
        // has no id until persisted; mimic the real Doctrine listener which
        // generates one on save.
        $this->sourceGateway
            ->method('save')
            ->willReturnCallback(function (Source $source): void {
                try {
                    $source->getId();
                } catch (\LogicException) {
                    $this->forcePropertyValue($source, 'src-' . bin2hex(random_bytes(4)));
                }
            });
        $this->collectTaskGateway = $this->createMock(CollectTaskGatewayInterface::class);
        $this->documentGateway = $this->createMock(DocumentGatewayInterface::class);
        $this->qualityReportGateway = $this->createMock(QualityReportGatewayInterface::class);
        $this->qualityReportGateway
            ->method('findByDocumentId')
            ->willThrowException(new QualityReportNotFoundException('No report'));
        $this->urlClassifier = $this->createStub(UrlSourceTypeClassifierInterface::class);
        // Default: any URL classifies as MANUAL → CLI accepts it.
        $this->urlClassifier->method('classify')
->willReturn(SourceType::MANUAL);

        $this->rebuildCommand();
    }

    public function testFailsWhenNeitherUrlNorHtmlFileProvided(): void
    {
        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString(
            'You must provide either --url or --html-file',
            $this->commandTester->getDisplay(),
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

    public function testDispatchesCreateCollectTaskActionAsynchronouslyByDefault(): void
    {
        $watchFile = $this->makeWatchFileWithManualSource();
        $this->watchFileGateway->method('get')
->willReturn($watchFile);

        $captured = null;
        $this->dispatchHandler = static function (object $message, array $stamps = []) use (&$captured): Envelope {
            $captured = [
                'message' => $message,
                'stamps' => $stamps,
            ];

            return new Envelope($message);
        };

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--url' => 'https://example.com/article',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNotNull($captured);
        $message = $captured['message'];
        self::assertInstanceOf(CreateCollectTaskAction::class, $message);
        self::assertSame('wf-1', $message->watchFileId);
        self::assertSame('src-existing', $message->sourceId);
        self::assertNotNull($message->configuration);
        self::assertSame('https://example.com/article', $message->configuration['url']);
        self::assertArrayNotHasKey('_sync_chain', $message->configuration);
        // Async path: no TransportNamesStamp.
        self::assertSame([], $captured['stamps']);
        self::assertStringContainsString(
            'Collect task dispatched for async ingestion',
            $this->commandTester->getDisplay(),
        );
    }

    public function testRejectsSocialUrlOnManualSourceWhenClassifierDetectsKnownPlatform(): void
    {
        $this->urlClassifier = $this->createStub(UrlSourceTypeClassifierInterface::class);
        $this->urlClassifier->method('classify')
->willReturn(SourceType::SOCIAL_MEDIA_TWITTER);
        $this->rebuildCommand();

        $this->watchFileGateway->method('get')
->willReturn($this->makeWatchFileWithManualSource());

        $dispatched = false;
        $this->dispatchHandler = static function (object $message) use (&$dispatched): Envelope {
            $dispatched = true;

            return new Envelope($message);
        };

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--url' => 'https://twitter.com/user/status/123',
        ]);

        self::assertFalse($dispatched, 'Bus must not be hit when classifier rejects the URL');

        self::assertSame(Command::FAILURE, $exitCode);
        // SymfonyStyle::error wraps long messages onto multiple lines; flatten
        // newlines + collapse repeated whitespace before asserting so we are
        // resilient to terminal width.
        $flattened = preg_replace('/\s+/', ' ', $this->commandTester->getDisplay()) ?? '';
        self::assertStringContainsString('not yet supported', $flattened);
        self::assertStringContainsString('social_media:twitter', $flattened);
    }

    public function testBypassesClassifierWhenSourceIdIsExplicit(): void
    {
        $watchFile = $this->makeWatchFile();
        $this->watchFileGateway->method('get')
->willReturn($watchFile);

        $explicitSource = $this->makeManualSource(
            $watchFile,
            name: 'Pinned Twitter',
            id: 'src-twitter',
            type: SourceType::SOCIAL_MEDIA_TWITTER,
        );
        $this->sourceGateway->method('get')
->with('src-twitter')
->willReturn($explicitSource);

        // Even though the URL is twitter.com, --source-id was set, so the
        // classifier check is skipped: we trust the operator.
        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--url' => 'https://twitter.com/user/status/123',
            '--source-id' => 'src-twitter',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Using source: Pinned Twitter', $this->commandTester->getDisplay());
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

    public function testReadsLocalHtmlFileAndPropagatesToConfiguration(): void
    {
        $watchFile = $this->makeWatchFileWithManualSource();
        $this->watchFileGateway->method('get')
->willReturn($watchFile);

        $captured = null;
        $this->dispatchHandler = static function (object $message) use (&$captured): Envelope {
            $captured = $message;

            return new Envelope($message);
        };

        $tmpFile = tempnam(sys_get_temp_dir(), 'create-doc-test-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, '<html><body>local content</body></html>');

        try {
            $exitCode = $this->commandTester->execute([
                'watchFileId' => 'wf-1',
                '--html-file' => $tmpFile,
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertInstanceOf(CreateCollectTaskAction::class, $captured);
            self::assertNotNull($captured->configuration);
            self::assertArrayHasKey('raw_html', $captured->configuration);
            self::assertSame('<html><body>local content</body></html>', $captured->configuration['raw_html']);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testSyncOptionPropagatesSyncChainAndPrintsRecapWhenCollectCompleted(): void
    {
        $watchFile = $this->makeWatchFileWithManualSource();
        $this->watchFileGateway->method('get')
->willReturn($watchFile);

        $completedTask = $this->makeCollectTask(CollectTaskStatus::COMPLETED);
        $this->collectTaskGateway->method('get')
->willReturn($completedTask);

        $persistedDocument = new Document(
            id: 'doc-sync',
            title: 'Sync article',
            excerpt: 'excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Content body that is long enough.',
        );
        $this->documentGateway->method('findByCollectTaskId')
->willReturn([$persistedDocument]);

        $captured = null;
        $this->dispatchHandler = static function (object $message, array $stamps) use (
            &$captured,
            $completedTask,
        ): Envelope {
            $captured = [
                'message' => $message,
                'stamps' => $stamps,
            ];
            $envelope = new Envelope($message);
            if ($message instanceof CreateCollectTaskAction) {
                $envelope = $envelope->with(new HandledStamp($completedTask, 'CreateCollectTaskHandler'));
            }

            return $envelope;
        };

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--url' => 'https://example.com/article',
            '--sync' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNotNull($captured);
        $message = $captured['message'];
        self::assertInstanceOf(CreateCollectTaskAction::class, $message);
        self::assertNotNull($message->configuration);
        self::assertTrue($message->configuration['_sync_chain']);
        $hasSyncStamp = false;
        foreach ($captured['stamps'] as $stamp) {
            if ($stamp instanceof TransportNamesStamp && \in_array('sync', $stamp->getTransportNames(), true)) {
                $hasSyncStamp = true;
            }
        }
        self::assertTrue(
            $hasSyncStamp,
            'CreateCollectTaskAction must carry TransportNamesStamp([sync]) when --sync is set',
        );
        self::assertStringContainsString('Document persisted: "Sync article"', $this->commandTester->getDisplay());
    }

    public function testSyncOptionReportsAsyncProviderWhenCollectIsStillRunning(): void
    {
        $watchFile = $this->makeWatchFileWithManualSource();
        $this->watchFileGateway->method('get')
->willReturn($watchFile);

        $runningTask = $this->makeCollectTask(CollectTaskStatus::QUEUED);
        $this->collectTaskGateway->method('get')
->willReturn($runningTask);

        $this->dispatchHandler = static fn (object $message): Envelope => new Envelope($message)
            ->with(new HandledStamp($runningTask, 'CreateCollectTaskHandler'));

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--url' => 'https://example.com/article',
            '--sync' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('scheduled async', $this->commandTester->getDisplay());
    }

    public function testSyncOptionWarnsWhenCollectCompletedButNoDocumentsPersisted(): void
    {
        $watchFile = $this->makeWatchFileWithManualSource();
        $this->watchFileGateway->method('get')
->willReturn($watchFile);

        $completedTask = $this->makeCollectTask(CollectTaskStatus::COMPLETED);
        $this->collectTaskGateway->method('get')
->willReturn($completedTask);
        $this->documentGateway->method('findByCollectTaskId')
->willReturn([]);

        $this->dispatchHandler = static fn (object $message): Envelope => new Envelope($message)
            ->with(new HandledStamp($completedTask, 'CreateCollectTaskHandler'));

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--url' => 'https://example.com/article',
            '--sync' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('halted', $this->commandTester->getDisplay());
    }

    public function testCreatesNewManualSourceWhenWatchFileHasNone(): void
    {
        $watchFile = $this->makeWatchFile();
        $this->watchFileGateway->method('get')
->willReturn($watchFile);

        $this->sourceGateway
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static fn (Source $s): bool => SourceType::MANUAL === $s->getType()));

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--url' => 'https://example.com/article',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Created new manual source', $this->commandTester->getDisplay());
    }

    public function testTitleAndExcerptOverridesArePropagatedToConfiguration(): void
    {
        $watchFile = $this->makeWatchFileWithManualSource();
        $this->watchFileGateway->method('get')
->willReturn($watchFile);

        $captured = null;
        $this->dispatchHandler = static function (object $message) use (&$captured): Envelope {
            $captured = $message;

            return new Envelope($message);
        };

        $exitCode = $this->commandTester->execute([
            'watchFileId' => 'wf-1',
            '--url' => 'https://example.com/article',
            '--title' => 'CLI Title',
            '--excerpt' => 'CLI Excerpt',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertInstanceOf(CreateCollectTaskAction::class, $captured);
        self::assertNotNull($captured->configuration);
        self::assertSame('CLI Title', $captured->configuration['title']);
        self::assertSame('CLI Excerpt', $captured->configuration['excerpt']);
    }

    private function rebuildCommand(): void
    {
        $this->command = new CreateDocumentCommand(
            messageBus: $this->messageBus,
            watchFileGateway: $this->watchFileGateway,
            sourceGateway: $this->sourceGateway,
            collectTaskGateway: $this->collectTaskGateway,
            documentGateway: $this->documentGateway,
            qualityReportGateway: $this->qualityReportGateway,
            manualSourceFactory: new ManualSourceFactory(),
            urlClassifier: $this->urlClassifier,
        );
        $this->commandTester = new CommandTester($this->command);
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

    private function makeManualSource(
        WatchFile $watchFile,
        string $name,
        string $id,
        SourceType $type = SourceType::MANUAL,
    ): Source {
        $source = new Source(
            name: $name,
            description: new TranslatedText('Source manuelle', 'Manual source'),
            type: $type,
            url: 'manual://' . $watchFile->getId(),
            primaryDomain: 'manual',
            relevance: new TranslatedText('Ajout manuel', 'Manual add'),
            actor: null,
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($source, $id);

        return $source;
    }

    private function makeCollectTask(CollectTaskStatus $status): CollectTask
    {
        $watchFile = $this->makeWatchFile();
        $source = $this->makeManualSource($watchFile, name: 'Manual', id: 'src-existing');
        $task = new CollectTask(
            source: $source,
            watchFile: $watchFile,
            providerName: 'web',
            providerTaskId: 'web-test-123',
            status: $status,
        );
        $this->forcePropertyValue($task, 'collect-task-test');

        return $task;
    }
}
