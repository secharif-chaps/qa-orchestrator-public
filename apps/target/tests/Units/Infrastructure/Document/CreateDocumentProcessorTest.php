<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document;

use ApiPlatform\Metadata\Operation;
use App\Application\Collect\Task\CreateCollectTaskAction;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Url\UrlSourceTypeClassifierInterface;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\ManualSourceFactory;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\Document\CreateDocumentProcessor;
use App\Tests\Utils\EntityUtilsTrait;
use App\UserInterface\Dto\Document\CreateDocumentInputDto;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(CreateDocumentProcessor::class)]
class CreateDocumentProcessorTest extends TestCase
{
    use EntityUtilsTrait;
    private MessageBusInterface&MockObject $messageBus;
    private Security&MockObject $security;
    private WatchFileGatewayInterface&MockObject $watchFileGateway;
    private SourceGatewayInterface&MockObject $sourceGateway;
    private DocumentGatewayInterface&MockObject $documentGateway;
    private UrlSourceTypeClassifierInterface&Stub $urlClassifier;
    private CreateDocumentProcessor $processor;

    /** @var \Closure(object, array<int, mixed>): Envelope */
    private \Closure $dispatchHandler;

    protected function setUp(): void
    {
        $this->dispatchHandler = static fn (object $message, array $stamps = []): Envelope => new Envelope($message);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->messageBus
            ->method('dispatch')
            ->willReturnCallback(
                fn (object $message, array $stamps = []): Envelope => ($this->dispatchHandler)($message, $stamps),
            );

        $this->security = $this->createMock(Security::class);
        $this->security->method('getUser')
->willReturn($this->createStub(User::class));
        $this->security->method('isGranted')
->willReturn(true);

        $this->watchFileGateway = $this->createMock(WatchFileGatewayInterface::class);
        $this->sourceGateway = $this->createMock(SourceGatewayInterface::class);
        $this->sourceGateway->method('save')
            ->willReturnCallback(function (Source $source): void {
                try {
                    $source->getId();
                } catch (\LogicException) {
                    $this->forcePropertyValue($source, 'src-' . bin2hex(random_bytes(4)));
                }
            });
        $this->documentGateway = $this->createMock(DocumentGatewayInterface::class);
        $this->urlClassifier = $this->createStub(UrlSourceTypeClassifierInterface::class);
        $this->urlClassifier->method('classify')
->willReturn(SourceType::MANUAL);

        $this->rebuildProcessor();
    }

    public function testRejectsRequestMissingBothUrlAndHtml(): void
    {
        $this->watchFileGateway->method('get')
->willReturn($this->makeWatchFileWithManualSource());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/url.*html|html.*url/');

        $this->processor->process(
            data: new CreateDocumentInputDto(url: null, html: null),
            operation: $this->createStub(Operation::class),
            uriVariables: [
                'watchFileId' => 'wf-1',
            ],
        );
    }

    public function testRejectsSocialUrlOnAutoResolvedManualSource(): void
    {
        $this->urlClassifier = $this->createStub(UrlSourceTypeClassifierInterface::class);
        $this->urlClassifier->method('classify')
->willReturn(SourceType::SOCIAL_MEDIA_TWITTER);
        $this->rebuildProcessor();

        $this->watchFileGateway->method('get')
->willReturn($this->makeWatchFileWithManualSource());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/social_media:twitter|not yet supported/');

        $this->processor->process(
            data: new CreateDocumentInputDto(url: 'https://twitter.com/u/status/123'),
            operation: $this->createStub(Operation::class),
            uriVariables: [
                'watchFileId' => 'wf-1',
            ],
        );
    }

    public function testDispatchesCreateCollectTaskActionAndReturnsPersistedDocument(): void
    {
        $watchFile = $this->makeWatchFileWithManualSource();
        $this->watchFileGateway->method('get')
->willReturn($watchFile);

        $persistedDocument = $this->makeDocument('doc-from-api');
        $this->documentGateway->method('findByCollectTaskId')
->willReturn([$persistedDocument]);

        $completedTask = $this->makeCollectTask(CollectTaskStatus::COMPLETED);
        $captured = null;
        $this->dispatchHandler = function (object $message, array $stamps) use (&$captured, $completedTask): Envelope {
            $captured = [
                'message' => $message,
                'stamps' => $stamps,
            ];

            return new Envelope($message)
                ->with(new HandledStamp($completedTask, 'CreateCollectTaskHandler'));
        };

        $document = $this->processor->process(
            data: new CreateDocumentInputDto(url: 'https://example.com/article'),
            operation: $this->createStub(Operation::class),
            uriVariables: [
                'watchFileId' => 'wf-1',
            ],
        );

        self::assertSame($persistedDocument, $document);
        self::assertNotNull($captured);
        $message = $captured['message'];
        self::assertInstanceOf(CreateCollectTaskAction::class, $message);
        self::assertSame('wf-1', $message->watchFileId);
        self::assertNotNull($message->configuration);
        self::assertSame('https://example.com/article', $message->configuration['url']);
        self::assertTrue($message->configuration['_sync_chain']);
        $hasSyncStamp = false;
        foreach ($captured['stamps'] as $stamp) {
            if ($stamp instanceof TransportNamesStamp && \in_array('sync', $stamp->getTransportNames(), true)) {
                $hasSyncStamp = true;
            }
        }
        self::assertTrue($hasSyncStamp);
    }

    public function testThrowsUnprocessableWhenPipelineProducesNoDocument(): void
    {
        $watchFile = $this->makeWatchFileWithManualSource();
        $this->watchFileGateway->method('get')
->willReturn($watchFile);

        $this->documentGateway->method('findByCollectTaskId')
->willReturn([]);

        $completedTask = $this->makeCollectTask(CollectTaskStatus::COMPLETED);
        $this->dispatchHandler = static fn (object $message): Envelope => new Envelope($message)
            ->with(new HandledStamp($completedTask, 'CreateCollectTaskHandler'));

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessageMatches('/halted|duplicate/');

        $this->processor->process(
            data: new CreateDocumentInputDto(url: 'https://example.com/article'),
            operation: $this->createStub(Operation::class),
            uriVariables: [
                'watchFileId' => 'wf-1',
            ],
        );
    }

    public function testBypassesClassifierWhenSourceIdIsExplicit(): void
    {
        $this->urlClassifier = $this->createStub(UrlSourceTypeClassifierInterface::class);
        $this->urlClassifier->method('classify')
->willReturn(SourceType::SOCIAL_MEDIA_TWITTER);
        $this->rebuildProcessor();

        $watchFile = $this->makeWatchFile();
        $this->watchFileGateway->method('get')
->willReturn($watchFile);
        $explicitSource = $this->makeSource(
            $watchFile,
            name: 'Pinned Twitter',
            id: 'src-twitter',
            type: SourceType::SOCIAL_MEDIA_TWITTER,
        );
        $this->sourceGateway->method('get')
->with('src-twitter')
->willReturn($explicitSource);

        $this->documentGateway->method('findByCollectTaskId')
            ->willReturn([$this->makeDocument('doc-explicit')]);

        $completedTask = $this->makeCollectTask(CollectTaskStatus::COMPLETED);
        $this->dispatchHandler = static fn (object $message): Envelope => new Envelope($message)
            ->with(new HandledStamp($completedTask, 'CreateCollectTaskHandler'));

        $document = $this->processor->process(
            data: new CreateDocumentInputDto(url: 'https://twitter.com/u/status/123', sourceId: 'src-twitter'),
            operation: $this->createStub(Operation::class),
            uriVariables: [
                'watchFileId' => 'wf-1',
            ],
        );

        self::assertSame('doc-explicit', $document->getId());
    }

    private function rebuildProcessor(): void
    {
        $this->processor = new CreateDocumentProcessor(
            messageBus: $this->messageBus,
            security: $this->security,
            watchFileGateway: $this->watchFileGateway,
            sourceGateway: $this->sourceGateway,
            documentGateway: $this->documentGateway,
            manualSourceFactory: new ManualSourceFactory(),
            urlClassifier: $this->urlClassifier,
            urlSanitizer: new \App\Infrastructure\Url\PhpUrlSanitizer(),
            logger: new NullLogger(),
        );
    }

    private function makeWatchFile(string $id = 'wf-1'): WatchFile
    {
        $organisation = new Organisation('Org', 'org-1');
        $watchFile = new WatchFile('wf', 'objective', $organisation);
        $this->forcePropertyValue($watchFile, $id);

        return $watchFile;
    }

    private function makeWatchFileWithManualSource(): WatchFile
    {
        $watchFile = $this->makeWatchFile();
        $watchFile->addSource($this->makeSource($watchFile, name: 'Manual', id: 'src-manual'));

        return $watchFile;
    }

    private function makeSource(
        WatchFile $watchFile,
        string $name,
        string $id,
        SourceType $type = SourceType::MANUAL,
    ): Source {
        $source = new Source(
            name: $name,
            description: new TranslatedText('desc fr', 'desc en'),
            type: $type,
            url: 'manual://' . $watchFile->getId(),
            primaryDomain: 'manual',
            relevance: new TranslatedText('rel fr', 'rel en'),
            actor: null,
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($source, $id);

        return $source;
    }

    private function makeCollectTask(CollectTaskStatus $status): CollectTask
    {
        $watchFile = $this->makeWatchFile();
        $source = $this->makeSource($watchFile, 'src', 'src-test');
        $task = new CollectTask(source: $source, watchFile: $watchFile, providerName: 'web', status: $status);
        $this->forcePropertyValue($task, 'collect-task-test');

        return $task;
    }

    private function makeDocument(string $id): Document
    {
        return new Document(
            id: $id,
            title: 'Test article',
            excerpt: 'Test excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content body',
        );
    }
}
