<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile;

use App\Application\WatchFile\Favorite\RemoveFavoriteWatchFileAction;
use App\Application\WatchFile\Favorite\RemoveFavoriteWatchFileHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\User\User;
use App\Domain\User\UserFavoriteWatchFile;
use App\Domain\User\UserFavoriteWatchFileNotFoundException;
use App\Domain\User\UserNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\User\NullUserFavoriteWatchFileGateway;
use App\Tests\Units\Infrastructure\User\NullUserGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class RemoveFavoriteWatchFileHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private NullUserGateway $userGateway;
    private NullUserFavoriteWatchFileGateway $favoriteGateway;
    private NullWatchFileGateway $watchFileGateway;
    private RemoveFavoriteWatchFileHandler $handler;

    protected function setUp(): void
    {
        $this->userGateway = new NullUserGateway();
        $this->favoriteGateway = new NullUserFavoriteWatchFileGateway();
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->handler = new RemoveFavoriteWatchFileHandler(
            $this->userGateway,
            $this->favoriteGateway,
            new NullLogger(),
        );
        $this->handler->setWatchFileGateway($this->watchFileGateway);
    }

    public function testRemoveFavoriteSuccess(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user = new User('user1', 'user1@example.com', [], 'user1');
        $this->userGateway->save($user);

        $favorite = new UserFavoriteWatchFile($user, $watchFile);
        $this->favoriteGateway->save($favorite);

        $action = new RemoveFavoriteWatchFileAction('watchfile1', 'user1');
        $result = ($this->handler)($action);
        $this->assertTrue($result);

        $this->expectException(UserFavoriteWatchFileNotFoundException::class);
        $this->favoriteGateway->getByUserAndWatchFile($user, $watchFile);
    }

    public function testRemoveFavoriteNotFoundDoesNotThrow(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $user = new User('user1', 'user1@example.com', [], 'user1');
        $this->userGateway->save($user);

        $action = new RemoveFavoriteWatchFileAction('watchfile1', 'user1');
        $result = ($this->handler)($action);

        $this->assertFalse($result);
    }

    public function testRemoveFavoriteThrowsIfWatchFileNotFound(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'user1');
        $this->userGateway->save($user);

        $action = new RemoveFavoriteWatchFileAction('unknown-watchfile', 'user1');
        $this->expectException(UnrecoverableMessageHandlingException::class);
        ($this->handler)($action);
    }

    public function testRemoveFavoriteThrowsIfUserNotFound(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $this->watchFileGateway->save($watchFile);

        $action = new RemoveFavoriteWatchFileAction('watchfile1', 'unknown-user');
        $this->expectException(UserNotFoundException::class);

        ($this->handler)($action);
    }
}
