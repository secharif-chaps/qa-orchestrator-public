<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\Workflow;

use App\Application\WatchFile\Favorite\AddFavoriteWatchFileAction;
use App\Application\WatchFile\Favorite\AddFavoriteWatchFileHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\User\User;
use App\Domain\User\UserFavoriteWatchFile;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\User\NullUserFavoriteWatchFileGateway;
use App\Tests\Units\Infrastructure\User\NullUserGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class AddFavoriteWatchFileHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private NullUserGateway $userGateway;
    private NullUserFavoriteWatchFileGateway $userFavoriteWatchFileGateway;
    private NullWatchFileGateway $watchFileGateway;
    private RealTimeUpdatePublisherInterface&Stub $realTimeUpdatePublisher;
    private AddFavoriteWatchFileHandler $handler;

    protected function setUp(): void
    {
        $this->userGateway = new NullUserGateway();
        $this->userFavoriteWatchFileGateway = new NullUserFavoriteWatchFileGateway();
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);
        $this->handler = new AddFavoriteWatchFileHandler(
            $this->userGateway,
            $this->userFavoriteWatchFileGateway,
            $this->realTimeUpdatePublisher,
            new NullLogger(),
        );
        $this->handler->setWatchFileGateway($this->watchFileGateway);
    }

    public function testAddFavoriteWatchFile(): void
    {
        $user = new User('user@example.com', 'password');
        $this->forcePropertyValue($user, 'user_id');
        $this->userGateway->save($user);

        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watchfile_id');
        $this->watchFileGateway->save($watchFile);

        $action = new AddFavoriteWatchFileAction(watchFileId: 'watchfile_id', userId: 'user_id');

        $result = ($this->handler)($action);

        $this->assertTrue($result);
    }

    public function testAddFavoriteWatchFileWithInvalidUserThrowsException(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watchfile_id');
        $this->watchFileGateway->save($watchFile);

        $action = new AddFavoriteWatchFileAction(watchFileId: 'watchfile_id', userId: 'invalid-user-id');

        $this->expectException(\App\Domain\User\UserNotFoundException::class);
        $this->expectExceptionMessage('User not found for id invalid-user-id');

        ($this->handler)($action);
    }

    public function testAddFavoriteWatchFileWithInvalidWatchFileThrowsException(): void
    {
        $user = new User('user@example.com', 'password');
        $this->forcePropertyValue($user, 'user_id');
        $this->userGateway->save($user);

        $action = new AddFavoriteWatchFileAction(watchFileId: 'invalid-watchfile-id', userId: 'user_id');

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('WatchFile not found');

        ($this->handler)($action);
    }

    public function testAddFavoriteWatchFileAlreadyExists(): void
    {
        $user = new User('user@example.com', 'password');
        $this->forcePropertyValue($user, 'user_id');
        $this->userGateway->save($user);

        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watchfile_id');
        $this->watchFileGateway->save($watchFile);

        $existingFavorite = new UserFavoriteWatchFile($user, $watchFile);
        $this->userFavoriteWatchFileGateway->save($existingFavorite);

        $action = new AddFavoriteWatchFileAction(watchFileId: 'watchfile_id', userId: 'user_id');

        $result = ($this->handler)($action);

        $this->assertFalse($result);
    }
}
