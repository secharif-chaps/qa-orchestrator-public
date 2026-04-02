<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile;

use App\Application\WatchFile\Share\RemoveShareWatchFileAction;
use App\Application\WatchFile\Share\RemoveShareWatchFileHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\User\User;
use App\Domain\User\UserNotFoundException;
use App\Domain\WatchFile\Exception\CannotRemoveOwnerException;
use App\Domain\WatchFile\Exception\WatchFileUserNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Tests\Units\Infrastructure\Shared\NullEventDispatcher;
use App\Tests\Units\Infrastructure\Shared\NullNotifier;
use App\Tests\Units\Infrastructure\User\NullUserGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileUserGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class RemoveShareWatchFileHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private NullWatchFileUserGateway $watchFileUserGateway;
    private NullUserGateway $userGateway;
    private NullWatchFileGateway $watchFileGateway;
    private NullNotifier $notifier;
    private NullEventDispatcher $eventDispatcher;
    private DummyNotificationFactory $notificationFactory;
    private RealTimeUpdatePublisherInterface&Stub $realTimeUpdatePublisher;
    private RemoveShareWatchFileHandler $handler;

    protected function setUp(): void
    {
        $this->watchFileUserGateway = new NullWatchFileUserGateway();
        $this->userGateway = new NullUserGateway();
        $this->notifier = new NullNotifier();
        $this->eventDispatcher = new NullEventDispatcher();
        $this->notificationFactory = new DummyNotificationFactory();
        $this->realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);
        $this->handler = new RemoveShareWatchFileHandler(
            $this->watchFileUserGateway,
            $this->userGateway,
            $this->notifier,
            $this->notificationFactory,
            $this->eventDispatcher,
            $this->realTimeUpdatePublisher,
            new NullLogger(),
        );

        $this->watchFileGateway = new NullWatchFileGateway();
        $this->handler->setWatchFileGateway($this->watchFileGateway);
    }

    public function testRemoveShareSuccess(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user = new User('user1', 'user1@example.com', [], 'user1');
        $this->forcePropertyValue($user, 'user1');
        $this->userGateway->save($user);

        $removedBy = new User('remover', 'remover@example.com', [], 'remover');
        $this->forcePropertyValue($removedBy, 'remover');
        $this->userGateway->save($removedBy);

        $watchFileUser = new WatchFileUser($watchFile, $user, WatchFileUserRole::EDITOR);
        $this->forcePropertyValue($watchFileUser, 'watchFileUser1');
        $watchFile->addWatchFileUser($watchFileUser);
        $this->watchFileUserGateway->save($watchFileUser);

        $action = new RemoveShareWatchFileAction('watchfile1', 'watchFileUser1', 'remover', new \DateTimeImmutable());

        ($this->handler)($action);

        $this->assertArrayNotHasKey('watchFileUser1', $this->watchFileUserGateway->watchFileUsers);
        $this->assertCount(1, $this->notifier->sent);
        $this->assertCount(1, $this->notificationFactory->removed);
    }

    public function testRemoveShareThrowsIfWatchFileIdMismatch(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user = new User('user1', 'user1@example.com', [], 'user1');
        $this->forcePropertyValue($user, 'user1');
        $this->userGateway->save($user);

        $removedBy = new User('remover', 'remover@example.com', [], 'remover');
        $this->forcePropertyValue($removedBy, 'remover');
        $this->userGateway->save($removedBy);

        $watchFileUser = new WatchFileUser($watchFile, $user, WatchFileUserRole::EDITOR);
        $this->forcePropertyValue($watchFileUser, 'watchFileUser1');
        $watchFile->addWatchFileUser($watchFileUser);
        $this->watchFileUserGateway->save($watchFileUser);

        $action = new RemoveShareWatchFileAction(
            'other-watchfile',
            'watchFileUser1',
            'remover',
            new \DateTimeImmutable()
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);

        ($this->handler)($action);
    }

    public function testRemoveShareThrowsIfOwner(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user = new User('user1', 'user1@example.com', [], 'user1');
        $this->forcePropertyValue($user, 'user1');
        $this->userGateway->save($user);

        $removedBy = new User('remover', 'remover@example.com', [], 'remover');
        $this->forcePropertyValue($removedBy, 'remover');
        $this->userGateway->save($removedBy);

        $watchFileUser = new WatchFileUser($watchFile, $user, WatchFileUserRole::OWNER);
        $this->forcePropertyValue($watchFileUser, 'watchFileUser1');
        $watchFile->addWatchFileUser($watchFileUser);
        $this->watchFileUserGateway->save($watchFileUser);

        $action = new RemoveShareWatchFileAction('watchfile1', 'watchFileUser1', 'remover', new \DateTimeImmutable());

        $this->expectException(CannotRemoveOwnerException::class);
        ($this->handler)($action);
    }

    public function testRemoveShareThrowsIfWatchFileUserNotFound(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $removedBy = new User('remover', 'remover@example.com', [], 'remover');
        $this->forcePropertyValue($removedBy, 'remover');
        $this->userGateway->save($removedBy);

        $action = new RemoveShareWatchFileAction(
            'watchfile1',
            'unknown-watchFileUser',
            'remover',
            new \DateTimeImmutable(),
        );

        $this->expectException(WatchFileUserNotFoundException::class);
        ($this->handler)($action);
    }

    public function testRemoveShareNoNotificationIfNoEmail(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user = new User('user1', null, [], 'user1');
        $this->forcePropertyValue($user, 'user1');
        $this->userGateway->save($user);

        $removedBy = new User('remover', 'remover@example.com', [], 'remover');
        $this->forcePropertyValue($removedBy, 'remover');
        $this->userGateway->save($removedBy);

        $watchFileUser = new WatchFileUser($watchFile, $user, WatchFileUserRole::EDITOR);
        $this->forcePropertyValue($watchFileUser, 'watchFileUser1');
        $watchFile->addWatchFileUser($watchFileUser);
        $this->watchFileUserGateway->save($watchFileUser);

        $action = new RemoveShareWatchFileAction('watchfile1', 'watchFileUser1', 'remover', new \DateTimeImmutable());
        ($this->handler)($action);

        $this->assertCount(0, $this->notifier->sent);
        $this->assertCount(0, $this->notificationFactory->removed);
    }

    public function testRemoveShareThrowsIfWatchFileNotFound(): void
    {
        $action = new RemoveShareWatchFileAction(
            'unknown-watchfile',
            'watchFileUser1',
            'remover',
            new \DateTimeImmutable(),
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        ($this->handler)($action);
    }

    public function testRemoveShareThrowsIfRemovedByUserNotFound(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user = new User('user1', 'user1@example.com', [], 'user1');
        $this->forcePropertyValue($user, 'user1');
        $this->userGateway->save($user);

        $watchFileUser = new WatchFileUser($watchFile, $user, WatchFileUserRole::EDITOR);
        $this->forcePropertyValue($watchFileUser, 'watchFileUser1');
        $watchFile->addWatchFileUser($watchFileUser);
        $this->watchFileUserGateway->save($watchFileUser);

        $action = new RemoveShareWatchFileAction(
            'watchfile1',
            'watchFileUser1',
            'unknown-remover',
            new \DateTimeImmutable(),
        );

        $this->expectException(UserNotFoundException::class);
        ($this->handler)($action);
    }
}
