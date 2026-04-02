<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Symfony\Security\Exception\AccessDeniedException;
use App\Domain\Organisation\Organisation;
use App\Domain\User\User;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Infrastructure\WatchFile\WatchFileUserProvider;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\MockHelpersTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

class WatchFileUserProviderTest extends TestCase
{
    use EntityUtilsTrait;
    use MockHelpersTrait;
    private Security $security;
    private NullWatchFileUserGateway $watchFileUserGateway;
    private NullWatchFileGateway $watchFileGateway;
    private WatchFileUserProvider $provider;

    protected function setUp(): void
    {
        $this->security = $this->createStub(Security::class);
        $this->watchFileUserGateway = new NullWatchFileUserGateway();
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->buildProvider();
    }

    private function buildProvider(): void
    {
        $this->provider = new WatchFileUserProvider(
            $this->security,
            $this->watchFileGateway,
            $this->watchFileUserGateway
        );
    }

    public function testProvideReturnsWatchFileUsersWhenAccessGranted(): void
    {
        $security = $this->createStub(Security::class);
        $this->security = $security;
        $this->buildProvider();

        $user = new User('user1', 'user1@example.com', [], 'User 1');

        $watchFileId = '9e4e8646-f76f-4920-8fc8-9e1828b63532';
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, $watchFileId);
        $this->watchFileGateway->save($watchFile);

        $watchFileUser = new WatchFileUser($watchFile, $user, WatchFileUserRole::EDITOR);
        $this->forcePropertyValue($watchFileUser, 'fu1');
        $watchFile->addWatchFileUser($watchFileUser);
        $this->watchFileUserGateway->save($watchFileUser);

        $security->method('getUser')
            ->willReturn($user);

        $security->method('isGranted')
            ->willReturn(true);

        $operation = $this->createStub(Operation::class);
        $result = $this->provider->provide($operation, [
            'id' => $watchFileId,
        ]);

        $this->assertCount(1, $result);
        $this->assertSame($watchFileUser, $result[0]);
    }

    public function testProvideThrowsIfWatchFileIdInvalid(): void
    {
        $security = $this->createStub(Security::class);
        $this->security = $security;
        $this->buildProvider();

        $security->method('getUser')
            ->willReturn(new User('user1'));

        $operation = $this->createStub(Operation::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The watch file ID "not-a-uuid" is not a valid UUID.');

        $this->provider->provide($operation, [
            'id' => 'not-a-uuid',
        ]);
    }

    public function testProvideThrowsIfUserNotAuthenticated(): void
    {
        $security = $this->createStub(Security::class);
        $this->security = $security;
        $this->buildProvider();

        $security->method('getUser')
            ->willReturn(null);

        $operation = $this->createStub(Operation::class);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('The user must be authenticated.');

        $this->provider->provide($operation, [
            'id' => Uuid::v4()->toString(),
        ]);
    }

    public function testProvideThrowsIfWatchFileNotFound(): void
    {
        $security = $this->createStub(Security::class);
        $this->security = $security;
        $this->buildProvider();

        $user = new User('user1');

        $security->method('getUser')
            ->willReturn($user);

        $operation = $this->createStub(Operation::class);

        $watchFileId = Uuid::v4()->toString();
        $this->expectException(WatchFileNotFoundException::class);
        $this->expectExceptionMessage(\sprintf('WatchFile with id %s not found', $watchFileId));
        $this->provider->provide($operation, [
            'id' => $watchFileId,
        ]);
    }

    public function testProvideThrowsIfAccessDenied(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFileId = Uuid::v4()->toString();

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, $watchFileId);
        $this->watchFileGateway->save($watchFile);

        $watchFileUser = new WatchFileUser($watchFile, $user, WatchFileUserRole::EDITOR);
        $this->forcePropertyValue($watchFileUser, 'fu1');
        $watchFile->addWatchFileUser($watchFileUser);
        $this->watchFileUserGateway->save($watchFileUser);

        $security = $this->createStub(Security::class);
        $security->method('getUser')
            ->willReturn($user);
        $security->method('isGranted')
            ->willReturn(false);
        $this->security = $security;
        $this->buildProvider();

        $operation = $this->createStub(Operation::class);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('The user must be granted access to the watch file.');

        $this->provider->provide($operation, [
            'id' => $watchFileId,
        ]);
    }
}
