<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFileActivity;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use App\Domain\Organisation\Organisation;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\Infrastructure\WatchFileActivity\WatchFileActivityProvider;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\ArrayPaginator;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\InvalidArgumentException;

class WatchFileActivityProviderTest extends TestCase
{
    use EntityUtilsTrait;
    private WatchFileActivityGatewayInterface&Stub $watchFileActivityGateway;
    private WatchFileGatewayInterface $watchFileGateway;
    private Security $security;
    private WatchFileActivityProvider $provider;

    protected function setUp(): void
    {
        $this->watchFileActivityGateway = $this->createStub(WatchFileActivityGatewayInterface::class);
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->security = $this->createStub(Security::class);
        $this->buildProvider();
    }

    private function buildProvider(): void
    {
        $this->provider = new WatchFileActivityProvider(
            $this->watchFileActivityGateway,
            $this->watchFileGateway,
            $this->security,
            new Pagination()
        );
    }

    public function testProvideWithNullWatchFileId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('WatchFile ID must be provided');

        $this->provider->provide($this->createStub(Operation::class), []);
    }

    public function testProvideWithNonExistentWatchFile(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('WatchFile not found');

        $this->provider->provide($this->createStub(Operation::class), [
            'watchFileId' => 'non-existent-id',
        ]);
    }

    public function testProvideWithAccessDenied(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('You do not have access to this watch file.');

        $this->provider->provide($this->createStub(Operation::class), [
            'watchFileId' => $watchFile->getId(),
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
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);
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

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        // Use the reusable ArrayPaginator for tests
        $paginator = new ArrayPaginator([$activity1, $activity2]);

        $watchFileActivityGatewayMock->expects($this->once())
            ->method('getByWatchFilePaginated')
            ->with($watchFile, 1, 30)
            ->willReturn($paginator);

        $result = $this->provider->provide(
            $this->createStub(Operation::class),
            [
                'watchFileId' => $watchFile->getId(),
            ]
        );

        $this->assertEquals(2, $result->count());
    }
}
