<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile;

use App\Application\WatchFile\Favorite\AddFavoriteWatchFileAction;
use App\Application\WatchFile\Favorite\AddFavoriteWatchFileHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\User\User;
use App\Domain\User\UserFavoriteWatchFile;
use App\Domain\User\UserNotFoundException;
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
    private NullUserFavoriteWatchFileGateway $favoriteGateway;
    private NullWatchFileGateway $watchFileGateway;
    private RealTimeUpdatePublisherInterface&Stub $realTimeUpdatePublisher;
    private AddFavoriteWatchFileHandler $handler;

    protected function setUp(): void
    {
        $this->userGateway = new NullUserGateway();
        $this->favoriteGateway = new NullUserFavoriteWatchFileGateway();
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);
        $this->handler = new AddFavoriteWatchFileHandler(
            $this->userGateway,
            $this->favoriteGateway,
            $this->realTimeUpdatePublisher,
            new NullLogger(),
        );
        $this->handler->setWatchFileGateway($this->watchFileGateway);
    }

    public function testAddFavoriteSuccess(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user = new User('user1', 'user1@example.com', [], 'user1');
        $this->userGateway->save($user);

        $action = new AddFavoriteWatchFileAction('watchfile1', 'user1');
        $result = ($this->handler)($action);
        $this->assertTrue($result);

        $favorite = $this->favoriteGateway->getByUserAndWatchFile($user, $watchFile);
        $this->assertSame($user, $favorite->getUser());
        $this->assertSame($watchFile, $favorite->getWatchFile());
    }

    public function testAddFavoriteAlreadyExists(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user = new User('user1', 'user1@example.com', [], 'user1');
        $this->userGateway->save($user);

        $favorite = new UserFavoriteWatchFile($user, $watchFile);
        $this->favoriteGateway->save($favorite);

        $action = new AddFavoriteWatchFileAction('watchfile1', 'user1');
        $result = ($this->handler)($action);

        $this->assertFalse($result);
        $this->assertSame($favorite, $this->favoriteGateway->getByUserAndWatchFile($user, $watchFile));
    }

    public function testAddFavoriteThrowsIfWatchFileNotFound(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'user1');
        $this->userGateway->save($user);

        $action = new AddFavoriteWatchFileAction('unknown-watchfile', 'user1');

        $this->expectException(UnrecoverableMessageHandlingException::class);
        ($this->handler)($action);
    }

    public function testAddFavoriteThrowsIfUserNotFound(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $action = new AddFavoriteWatchFileAction('watchfile1', 'unknown-user');
        $this->expectException(UserNotFoundException::class);

        ($this->handler)($action);
    }
}
