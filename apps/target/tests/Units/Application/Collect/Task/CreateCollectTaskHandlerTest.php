<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect\Task;

use App\Application\Collect\Task\CreateCollectTaskAction;
use App\Application\Collect\Task\CreateCollectTaskHandler;
use App\Domain\Actor\Actor;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Event\CollectTaskCompletedEvent;
use App\Domain\Collect\Event\CollectTaskCreatedEvent;
use App\Domain\Collect\Event\CollectTaskStartedEvent;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\Exception\InactiveWatchFileException;
use App\Domain\Collect\ProviderGatewayInterface;
use App\Domain\Collect\ProviderGatewayLocatorInterface;
use App\Domain\Collect\ProviderResolverInterface;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceNotFoundException;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Tests\Units\Infrastructure\Collect\NullCollectTaskGateway;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateCollectTaskHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private NullCollectTaskGateway $collectTaskGateway;
    private NullSourceGateway $sourceGateway;
    private NullWatchFileGateway $watchFileGateway;
    private ProviderResolverInterface&Stub $providerResolver;
    private ProviderGatewayLocatorInterface&Stub $providerLocator;
    private ProviderGatewayInterface&Stub $providerGateway;
    private EventDispatcherInterface&Stub $eventDispatcher;
    private MessageBusInterface&Stub $messageBus;
    private CreateCollectTaskHandler $handler;

    protected function setUp(): void
    {
        $this->collectTaskGateway = new NullCollectTaskGateway();
        $this->sourceGateway = new NullSourceGateway();
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->providerResolver = $this->createStub(ProviderResolverInterface::class);
        $this->providerLocator = $this->createStub(ProviderGatewayLocatorInterface::class);
        $this->providerGateway = $this->createStub(ProviderGatewayInterface::class);
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->messageBus = $this->createStub(MessageBusInterface::class);

        $this->providerResolver->method('resolve')
->willReturn('bakus');
        $this->providerLocator->method('get')
->willReturn($this->providerGateway);

        $this->buildHandler();
    }

    private function buildHandler(): void
    {
        $this->handler = new CreateCollectTaskHandler(
            $this->collectTaskGateway,
            $this->sourceGateway,
            $this->watchFileGateway,
            $this->providerResolver,
            $this->providerLocator,
            $this->eventDispatcher,
            $this->messageBus
        );
    }

    private function buildEventDispatcherMock(): EventDispatcherInterface&MockObject
    {
        /** @var EventDispatcherInterface&MockObject $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildHandler();

        return $eventDispatcher;
    }

    private function buildProviderGatewayMock(): ProviderGatewayInterface&MockObject
    {
        /** @var ProviderGatewayInterface&MockObject $providerGateway */
        $providerGateway = $this->createMock(ProviderGatewayInterface::class);
        $this->providerGateway = $providerGateway;
        $this->buildHandler();

        return $providerGateway;
    }

    public function testCreateCollectTaskSuccess(): void
    {
        $eventDispatcher = $this->buildEventDispatcherMock();

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'desc fr',
                'en' => 'desc en',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel fr',
                'en' => 'rel en',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($source, 'source-id');
        $this->sourceGateway->save($source);

        $action = new CreateCollectTaskAction(sourceId: 'source-id', watchFileId: 'watchfile-id', start: false);

        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CollectTaskCreatedEvent::class));

        $handler = new CreateCollectTaskHandler(
            $this->collectTaskGateway,
            $this->sourceGateway,
            $this->watchFileGateway,
            $this->providerResolver,
            $this->providerLocator,
            $eventDispatcher,
            $this->messageBus
        );

        $result = $handler->__invoke($action);

        $this->assertInstanceOf(CollectTask::class, $result);
        $this->assertEquals('bakus', $result->getProviderName());
        $this->assertEquals([], $result->getConfiguration());
        $this->assertEquals(CollectTaskStatus::CREATED, $result->getStatus());
        $this->assertEquals($source, $result->getSource());
        $this->assertEquals($watchFile, $result->getWatchFile());

        $savedTasks = $this->collectTaskGateway->getAll();
        $this->assertCount(1, $savedTasks);
        $this->assertEquals($result->getId(), $savedTasks[0]->getId());
    }

    public function testCreateCollectTaskUsesResolvedProviderName(): void
    {
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'desc fr',
                'en' => 'desc en',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel fr',
                'en' => 'rel en',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($source, 'source-id');
        $this->sourceGateway->save($source);

        $providerResolver = $this->createMock(ProviderResolverInterface::class);
        $providerResolver->expects($this->once())
->method('resolve')
->with($source)
->willReturn('apify');

        $handler = new CreateCollectTaskHandler(
            $this->collectTaskGateway,
            $this->sourceGateway,
            $this->watchFileGateway,
            $providerResolver,
            $this->providerLocator,
            $this->eventDispatcher,
            $this->messageBus
        );

        $action = new CreateCollectTaskAction(sourceId: 'source-id', watchFileId: 'watchfile-id', start: false);

        $result = $handler->__invoke($action);

        $this->assertEquals('apify', $result->getProviderName());
    }

    public function testCreateCollectTaskWithNonExistentSource(): void
    {
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->watchFileGateway->save($watchFile);

        $action = new CreateCollectTaskAction(sourceId: 'non-existent-source', watchFileId: 'watchfile-id');

        $this->expectException(SourceNotFoundException::class);

        $this->handler->__invoke($action);
    }

    public function testCreateCollectTaskWithNonExistentWatchFile(): void
    {
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'desc fr',
                'en' => 'desc en',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel fr',
                'en' => 'rel en',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($source, 'source-id');
        $this->sourceGateway->save($source);

        $action = new CreateCollectTaskAction(sourceId: 'source-id', watchFileId: 'non-existent-watchfile');

        $this->expectException(WatchFileNotFoundException::class);

        $this->handler->__invoke($action);
    }

    public function testCreateCollectTaskWithInactiveWatchFile(): void
    {
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::DRAFT);
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'desc fr',
                'en' => 'desc en',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel fr',
                'en' => 'rel en',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($source, 'source-id');
        $this->sourceGateway->save($source);

        $action = new CreateCollectTaskAction(sourceId: 'source-id', watchFileId: 'watchfile-id');

        $this->expectException(InactiveWatchFileException::class);

        $this->handler->__invoke($action);
    }

    public function testCreateCollectTaskHandlesCollectExceptionGracefully(): void
    {
        $providerGateway = $this->buildProviderGatewayMock();

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'desc fr',
                'en' => 'desc en',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel fr',
                'en' => 'rel en',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($source, 'source-id');
        $this->sourceGateway->save($source);

        $handler = $this->handler;

        $action = new CreateCollectTaskAction(sourceId: 'source-id', watchFileId: 'watchfile-id', start: true);

        // Mock providerGateway to throw CollectException
        $providerGateway
            ->expects($this->once())
            ->method('createTask')
            ->willThrowException(new CollectException('Bakus API error'));

        // Expect events: Created + Completed (for fail)
        $providerLocator = $this->createStub(ProviderGatewayLocatorInterface::class);
        $providerLocator->method('get')
->willReturn($providerGateway);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    $this->assertInstanceOf(CollectTaskCreatedEvent::class, $event);
                } else {
                    $this->assertInstanceOf(CollectTaskCompletedEvent::class, $event);
                }

                return $event;
            });

        $handler = new CreateCollectTaskHandler(
            $this->collectTaskGateway,
            $this->sourceGateway,
            $this->watchFileGateway,
            $this->providerResolver,
            $providerLocator,
            $eventDispatcher,
            $this->messageBus
        );

        $result = ($handler)($action);

        // Verify the task was created but marked as FAILED
        $this->assertEquals(CollectTaskStatus::FAILED, $result->getStatus());

        // Verify the task was saved
        $savedTasks = $this->collectTaskGateway->getAll();
        $this->assertCount(1, $savedTasks);
        $this->assertEquals(CollectTaskStatus::FAILED, $savedTasks[0]->getStatus());
    }

    public function testCreateCollectTaskHandlesUnexpectedExceptionGracefully(): void
    {
        $providerGateway = $this->buildProviderGatewayMock();

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'desc fr',
                'en' => 'desc en',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel fr',
                'en' => 'rel en',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($source, 'source-id');
        $this->sourceGateway->save($source);

        $handler = $this->handler;

        $action = new CreateCollectTaskAction(sourceId: 'source-id', watchFileId: 'watchfile-id', start: true);

        // Mock providerGateway to throw unexpected exception
        $providerGateway
            ->expects($this->once())
            ->method('createTask')
            ->willThrowException(new \RuntimeException('Unexpected error'));

        // Expect events: Created + Completed (for fail)
        $providerLocator = $this->createStub(ProviderGatewayLocatorInterface::class);
        $providerLocator->method('get')
->willReturn($providerGateway);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    $this->assertInstanceOf(CollectTaskCreatedEvent::class, $event);
                } else {
                    $this->assertInstanceOf(CollectTaskCompletedEvent::class, $event);
                }

                return $event;
            });

        $handler = new CreateCollectTaskHandler(
            $this->collectTaskGateway,
            $this->sourceGateway,
            $this->watchFileGateway,
            $this->providerResolver,
            $providerLocator,
            $eventDispatcher,
            $this->messageBus
        );

        $result = ($handler)($action);

        // Verify the task was created but marked as FAILED
        $this->assertEquals(CollectTaskStatus::FAILED, $result->getStatus());

        // Verify the task was saved
        $savedTasks = $this->collectTaskGateway->getAll();
        $this->assertCount(1, $savedTasks);
        $this->assertEquals(CollectTaskStatus::FAILED, $savedTasks[0]->getStatus());
    }

    public function testCreateCollectTaskStartsSuccessfullyWithLogging(): void
    {
        $providerGateway = $this->buildProviderGatewayMock();

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'desc fr',
                'en' => 'desc en',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel fr',
                'en' => 'rel en',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($source, 'source-id');
        $this->sourceGateway->save($source);

        $handler = $this->handler;

        $action = new CreateCollectTaskAction(sourceId: 'source-id', watchFileId: 'watchfile-id', start: true);

        // Mock successful provider task creation
        $providerGateway
            ->expects($this->once())
            ->method('createTask')
            ->willReturn('bakus-task-id-123');
        $providerGateway
            ->expects($this->once())
            ->method('getTaskStatus')
            ->willReturn(CollectTaskStatus::QUEUED);

        // Expect events: Created + Started
        $providerLocator = $this->createStub(ProviderGatewayLocatorInterface::class);
        $providerLocator->method('get')
->willReturn($providerGateway);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    $this->assertInstanceOf(CollectTaskCreatedEvent::class, $event);
                } else {
                    $this->assertInstanceOf(CollectTaskStartedEvent::class, $event);
                }

                return $event;
            });

        $handler = new CreateCollectTaskHandler(
            $this->collectTaskGateway,
            $this->sourceGateway,
            $this->watchFileGateway,
            $this->providerResolver,
            $providerLocator,
            $eventDispatcher,
            $this->messageBus
        );

        $result = ($handler)($action);

        // Verify the task was started successfully
        $this->assertEquals(CollectTaskStatus::QUEUED, $result->getStatus());
        $this->assertEquals('bakus-task-id-123', $result->getProviderTaskId());

        // Verify the task was saved
        $savedTasks = $this->collectTaskGateway->getAll();
        $this->assertCount(1, $savedTasks);
        $this->assertEquals(CollectTaskStatus::QUEUED, $savedTasks[0]->getStatus());
    }
}
