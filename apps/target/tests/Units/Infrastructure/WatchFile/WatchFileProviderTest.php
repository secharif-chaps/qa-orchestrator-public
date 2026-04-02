<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Organisation\Organisation;
use App\Domain\User\User;
use App\Domain\User\UserFavoriteWatchFile;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Shared\EntityEnricherLocator;
use App\Infrastructure\Shared\EntityEnrichmentOrchestrator;
use App\Infrastructure\WatchFile\WatchFileProvider;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;

class WatchFileProviderTest extends TestCase
{
    use EntityUtilsTrait;
    private Security $security;

    /**
     * @var ProviderInterface<WatchFile>&MockObject
     */
    private ProviderInterface&MockObject $itemProvider;
    private WatchFileProvider $provider;

    protected function setUp(): void
    {
        $this->security = $this->createStub(Security::class);
        $this->itemProvider = $this->createMock(ProviderInterface::class);
        $this->buildProvider();
    }

    private function buildProvider(): void
    {
        $entityEnrichmentOrchestrator = new EntityEnrichmentOrchestrator(
            new EntityEnricherLocator([]),
            new NullLogger(),
        );
        $this->provider = new WatchFileProvider($this->itemProvider, $this->security, $entityEnrichmentOrchestrator);
    }

    public function testProvideItemWithAuthenticatedUserAndFavorite(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        // Add favorite for the user
        $userFavorite = new UserFavoriteWatchFile($user, $watchFile);
        $watchFile->addUserFavorite($userFavorite);

        $operation = new Get();
        $uriVariables = [
            'id' => 'watchfile1',
        ];
        $context = [];

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($user)
        ;

        $this->itemProvider->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn($watchFile)
        ;

        $result = $this->provider->provide($operation, $uriVariables, $context);

        $this->assertInstanceOf(WatchFile::class, $result);
    }

    public function testProvideItemWithAuthenticatedUserAndNotFavorite(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $operation = new Get();
        $uriVariables = [
            'id' => 'watchfile1',
        ];
        $context = [];

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($user)
        ;

        $this->itemProvider->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn($watchFile)
        ;

        $result = $this->provider->provide($operation, $uriVariables, $context);

        $this->assertInstanceOf(WatchFile::class, $result);
    }

    public function testProvideItemWithNoAuthenticatedUser(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $operation = new Get();
        $uriVariables = [
            'id' => 'watchfile1',
        ];
        $context = [];

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn(null)
        ;

        $this->itemProvider->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn($watchFile)
        ;

        $result = $this->provider->provide($operation, $uriVariables, $context);

        $this->assertInstanceOf(WatchFile::class, $result);
    }

    public function testProvideReturnsNullWhenProviderReturnsNull(): void
    {
        $operation = new Get();
        $uriVariables = [
            'id' => 'nonexistent',
        ];
        $context = [];

        $this->itemProvider->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn(null)
        ;

        $result = $this->provider->provide($operation, $uriVariables, $context);

        $this->assertNull($result);
    }
}
