<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile;

use App\Application\WatchFile\Share\ShareWatchFileAction;
use App\Application\WatchFile\Share\ShareWatchFileBatchAction;
use App\Application\WatchFile\Share\ShareWatchFileBatchHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\User\User;
use App\Domain\User\UserGatewayInterface;
use App\Domain\User\UserNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserGatewayInterface;
use App\Domain\WatchFile\WatchFileUserLimitExceededException;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Tests\Units\Infrastructure\Shared\NullEventDispatcher;
use App\Tests\Units\Infrastructure\Shared\NullNotifier;
use App\Tests\Units\Infrastructure\User\NullUserGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileUserGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class ShareWatchFileBatchHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private WatchFileUserGatewayInterface $watchFileUserGateway;
    private UserGatewayInterface $userGateway;
    private NullNotifier $notifier;
    private NullEventDispatcher $eventDispatcher;
    private DummyNotificationFactory $notificationFactory;
    private WatchFileGatewayInterface $watchFileGateway;
    private RealTimeUpdatePublisherInterface&Stub $realTimeUpdatePublisher;
    private ShareWatchFileBatchHandler $handler;

    protected function setUp(): void
    {
        $this->watchFileUserGateway = new NullWatchFileUserGateway();
        $this->userGateway = new NullUserGateway();
        $this->notifier = new NullNotifier();
        $this->eventDispatcher = new NullEventDispatcher();
        $this->notificationFactory = new DummyNotificationFactory();
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);
        $this->handler = new ShareWatchFileBatchHandler(
            $this->watchFileUserGateway,
            $this->userGateway,
            $this->notifier,
            $this->notificationFactory,
            $this->eventDispatcher,
            $this->realTimeUpdatePublisher,
        );

        $this->handler->setWatchFileGateway($this->watchFileGateway);
    }

    public function testAddNewUserToWatchFile(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user1 = new User('user1', 'user1@example.com', [], 'user1');
        $this->userGateway->save($user1);

        $user2 = new User('user2', 'user2@example.com', [], 'user2');
        $this->userGateway->save($user2);

        $action = new ShareWatchFileBatchAction(
            [new ShareWatchFileAction('watchfile1', 'user2', WatchFileUserRole::EDITOR)],
            'user1',
            new \DateTimeImmutable(),
        );

        $result = ($this->handler)($action);

        $this->assertCount(1, $result);
        $this->assertEquals('watchfile1', $result[0]->getWatchFile()?->getId());
        $this->assertEquals('user2', $result[0]->getUser()?->getId());
        $this->assertEquals(WatchFileUserRole::EDITOR, $result[0]->getRole());
        $this->assertEquals('user1', $result[0]->getCreatedBy()?->getId());
        $this->assertCount(1, $this->notifier->sent);
    }

    public function testUpdateUserRole(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user1 = new User('user1', 'user1@example.com', [], 'user1');
        $this->userGateway->save($user1);

        $user2 = new User('user2', 'user2@example.com', [], 'user2');
        $this->userGateway->save($user2);

        $watchFileUser = new WatchFileUser($watchFile, $user2, WatchFileUserRole::VIEWER);
        $this->forcePropertyValue($watchFileUser, 'watchfile-user-1');
        $this->watchFileUserGateway->save($watchFileUser);

        $action = new ShareWatchFileBatchAction(
            [new ShareWatchFileAction('watchfile1', 'user2', WatchFileUserRole::EDITOR)],
            'user1',
            new \DateTimeImmutable(),
        );

        $result = ($this->handler)($action);

        $this->assertCount(1, $result);
        $this->assertEquals(WatchFileUserRole::EDITOR, $result[0]->getRole());
        $this->assertEquals('user2', $result[0]->getUser()?->getId());
        $this->assertCount(1, $this->notifier->sent);
    }

    public function testNoNotificationForOwner(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user1 = new User('user1', 'user1@example.com', [], 'user1');
        $this->userGateway->save($user1);

        $action = new ShareWatchFileBatchAction(
            [new ShareWatchFileAction('watchfile1', 'user1', WatchFileUserRole::OWNER)],
            'user1',
            new \DateTimeImmutable(),
        );

        $result = ($this->handler)($action);

        $this->assertCount(1, $result);
        $this->assertEquals(WatchFileUserRole::OWNER, $result[0]->getRole());
        $this->assertEquals('user1', $result[0]->getUser()?->getId());
        $this->assertEquals('user1', $result[0]->getCreatedBy()?->getId());
        $this->assertCount(0, $this->notifier->sent);
    }

    public function testLimitExceededThrows(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user1 = new User('user1', 'user1@example.com', [], 'user1');
        $this->userGateway->save($user1);

        for ($i = 0; $i < WatchFile::MAX_WATCHFILE_USERS + 1; ++$i) {
            $fakeWatchFileUser = new WatchFileUser(
                $watchFile,
                new User('fake' . $i, 'fake-user' . $i . '@example.com', [], 'fake-user' . $i),
                WatchFileUserRole::VIEWER,
            );

            $this->forcePropertyValue($fakeWatchFileUser, 'fake-watchfile-user-' . $i);

            $this->watchFileUserGateway->save($fakeWatchFileUser);
        }

        $action = new ShareWatchFileBatchAction(
            [new ShareWatchFileAction('watchfile1', 'user1', WatchFileUserRole::OWNER)],
            'user1',
            new \DateTimeImmutable(),
        );

        $this->expectException(WatchFileUserLimitExceededException::class);
        ($this->handler)($action);
    }

    public function testUserToShareNotFoundThrows(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user1 = new User('user1', 'user1@example.com', [], 'user1');
        $this->userGateway->save($user1);

        $action = new ShareWatchFileBatchAction(
            [new ShareWatchFileAction('watchfile1', 'unknown-user-id', WatchFileUserRole::OWNER)],
            'user1',
            new \DateTimeImmutable(),
        );

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found for id unknown-user-id');

        ($this->handler)($action);
    }

    public function testUserSharedByNotFoundThrows(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user2 = new User('user2', 'user2@example.com', [], 'user2');
        $this->userGateway->save($user2);

        $action = new ShareWatchFileBatchAction(
            [new ShareWatchFileAction('watchfile1', 'user2', WatchFileUserRole::OWNER)],
            'unknown-user-id-shared-by',
            new \DateTimeImmutable(),
        );

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found for id unknown-user-id');

        ($this->handler)($action);
    }

    public function testWatchFileNotFoundThrows(): void
    {
        $user2 = new User('user2', 'user2@example.com', [], 'user2');
        $this->userGateway->save($user2);

        $user5 = new User('user5', 'user5@example.com', [], 'user5');
        $this->userGateway->save($user5);

        $action = new ShareWatchFileBatchAction(
            [new ShareWatchFileAction('unknown-watchfile', 'user5', WatchFileUserRole::OWNER)],
            'user5',
            new \DateTimeImmutable(),
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);

        ($this->handler)($action);
    }

    public function testShareWatchFileWithOwnerRole(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user1 = new User('user1', 'user1@example.com', [], 'user1');
        $this->userGateway->save($user1);

        $action = new ShareWatchFileBatchAction(
            [new ShareWatchFileAction('watchfile1', 'user1', WatchFileUserRole::OWNER)],
            'user1',
            new \DateTimeImmutable(),
        );

        $result = ($this->handler)($action);

        $this->assertCount(1, $result);
        $this->assertEquals('watchfile1', $result[0]->getWatchFile()?->getId());
        $this->assertEquals('user1', $result[0]->getUser()?->getId());
        $this->assertEquals(WatchFileUserRole::OWNER, $result[0]->getRole());
        $this->assertEquals('user1', $result[0]->getCreatedBy()?->getId());
        $this->assertCount(0, $this->notifier->sent);
    }
}
