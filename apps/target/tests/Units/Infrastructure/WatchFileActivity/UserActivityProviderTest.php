<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFileActivity;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use App\Domain\Organisation\Organisation;
use App\Domain\User\User;
use App\Domain\User\UserNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;
use App\Infrastructure\User\ConnectedUserVoter;
use App\Infrastructure\WatchFileActivity\UserActivityProvider;
use App\Tests\Units\Infrastructure\User\NullUserGateway;
use App\Tests\Utils\ArrayPaginator;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\InvalidArgumentException;

class UserActivityProviderTest extends TestCase
{
    use EntityUtilsTrait;
    private WatchFileActivityGatewayInterface&Stub $watchFileActivityGateway;
    private NullUserGateway $userGateway;
    private UserActivityProvider $provider;
    private Security $security;

    protected function setUp(): void
    {
        $this->watchFileActivityGateway = $this->createStub(WatchFileActivityGatewayInterface::class);
        $this->userGateway = new NullUserGateway();
        $this->security = $this->createStub(Security::class);
        $this->buildProvider();
    }

    private function buildProvider(): void
    {
        $this->provider = new UserActivityProvider(
            $this->watchFileActivityGateway,
            $this->userGateway,
            new Pagination(),
            $this->security
        );
    }

    public function testProvideWithNullUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID must be provided');

        $this->provider->provide($this->createStub(Operation::class), []);
    }

    public function testProvideWithNonExistentUser(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->provider->provide($this->createStub(Operation::class), [
            'userId' => 'non-existent-id',
        ]);
    }

    public function testProvideWithAccessDenied(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $user = new User('user-name', 'user-email', []);
        $this->forcePropertyValue($user, 'user-id');
        $this->userGateway->save($user);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(ConnectedUserVoter::CONNECTED_USER, $user)
            ->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('You do not have access to this user.');

        $this->provider->provide($this->createStub(Operation::class), [
            'userId' => 'user-id',
        ]);
    }

    public function testProvideSuccessfully(): void
    {
        $watchFileActivityGatewayMock = $this->createMock(WatchFileActivityGatewayInterface::class);
        $this->watchFileActivityGateway = $watchFileActivityGatewayMock;
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $user = new User('user-name', 'user-email', []);
        $this->forcePropertyValue($user, 'user-id');
        $this->userGateway->save($user);
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $activity1 = new WatchFileActivity(
            $watchFile,
            $user,
            WatchFileActivityActionType::CREATED,
            [],
            new Organisation('Test Org', 'test-org-id')
        );
        $activity2 = new WatchFileActivity(
            $watchFile,
            $user,
            WatchFileActivityActionType::CREATED,
            [],
            new Organisation('Test Org', 'test-org-id')
        );

        // Use the reusable ArrayPaginator for tests
        $paginator = new ArrayPaginator([$activity1, $activity2]);

        $watchFileActivityGatewayMock->expects($this->once())
            ->method('getByUserPaginated')
            ->with($user, 1, 30)
            ->willReturn($paginator);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(ConnectedUserVoter::CONNECTED_USER, $user)
            ->willReturn(true);

        $result = $this->provider->provide($this->createStub(Operation::class), [
            'userId' => 'user-id',
        ]);

        $this->assertEquals(2, $result->count());
    }
}
