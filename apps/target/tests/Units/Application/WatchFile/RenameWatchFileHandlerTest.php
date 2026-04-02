<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile;

use App\Application\WatchFile\RenameWatchFileAction;
use App\Application\WatchFile\RenameWatchFileHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\MockHelpersTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class RenameWatchFileHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    use MockHelpersTrait;
    private WatchFileGatewayInterface $watchFileGateway;
    private EventDispatcherInterface&Stub $eventDispatcher;
    private RealTimeUpdatePublisherInterface&Stub $realTimeUpdatePublisher;
    private LoggerInterface&Stub $logger;
    private RenameWatchFileHandler $handler;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->buildHandler();
    }

    private function buildHandler(): void
    {
        $this->handler = new RenameWatchFileHandler(
            $this->eventDispatcher,
            $this->realTimeUpdatePublisher,
            $this->logger,
        );
        $this->handler->setWatchFileGateway($this->watchFileGateway);
    }

    public function testRenameWatchFile(): void
    {
        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildHandler();

        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('old-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $action = new RenameWatchFileAction(watchFileId: 'watch_file_id', name: 'new-name');

        // Expect the event dispatcher to be called
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof \App\Domain\WatchFile\Event\WatchFileUpdatedEvent
                    && 'old-name' === $event->changes['name']['old']
                    && 'new-name' === $event->changes['name']['new'];
            }));

        $updatedWatchFile = ($this->handler)($action);

        $this->assertEquals('new-name', $updatedWatchFile->getName());
        $this->assertEquals('watchfile-objective', $updatedWatchFile->getUserObjective());
        $this->assertEquals('watch_file_id', $updatedWatchFile->getId());
    }

    public function testWatchFileNotFoundThrowsException(): void
    {
        $action = new RenameWatchFileAction(watchFileId: 'invalid-watchfile-id', name: 'new-name');

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('WatchFile not found');

        ($this->handler)($action);
    }

    public function testRenameWatchFileWithoutCreatedByUser(): void
    {
        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildHandler();

        $watchFile = new WatchFile('old-name', 'watchfile-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        // Don't set createdBy user
        $this->watchFileGateway->save($watchFile);

        $action = new RenameWatchFileAction(watchFileId: 'watch_file_id', name: 'new-name');

        // Expect the event dispatcher NOT to be called since there's no user
        $eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $updatedWatchFile = ($this->handler)($action);

        $this->assertEquals('new-name', $updatedWatchFile->getName());
    }
}
