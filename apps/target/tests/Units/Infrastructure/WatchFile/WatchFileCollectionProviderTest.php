<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Organisation\Organisation;
use App\Domain\User\User;
use App\Domain\User\UserFavoriteWatchFile;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Shared\EntityEnricherLocator;
use App\Infrastructure\Shared\EntityEnrichmentOrchestrator;
use App\Infrastructure\WatchFile\WatchFileCollectionProvider;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;

class WatchFileCollectionProviderTest extends TestCase
{
    use EntityUtilsTrait;
    private Security $security;

    /**
     * @var ProviderInterface<WatchFile>&MockObject
     */
    private ProviderInterface&MockObject $collectionProvider;
    private WatchFileCollectionProvider $provider;

    protected function setUp(): void
    {
        $this->security = $this->createStub(Security::class);
        $this->collectionProvider = $this->createMock(ProviderInterface::class);
        $this->buildProvider();
    }

    private function buildProvider(): void
    {
        $entityEnrichmentOrchestrator = new EntityEnrichmentOrchestrator(
            new EntityEnricherLocator([]),
            new NullLogger(),
        );
        $this->provider = new WatchFileCollectionProvider(
            $this->collectionProvider,
            $this->security,
            $entityEnrichmentOrchestrator,
        );
    }

    public function testProvideCollectionWithAuthenticatedUser(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile1 = new WatchFile('Test WatchFile 1', 'Test objective 1', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile1, 'watchfile1');

        $watchFile2 = new WatchFile('Test WatchFile 2', 'Test objective 2', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile2, 'watchfile2');

        // Add favorite for the first watch file
        $userFavorite = new UserFavoriteWatchFile($user, $watchFile1);
        $watchFile1->addUserFavorite($userFavorite);

        $watchFiles = [$watchFile1, $watchFile2];

        $operation = new GetCollection();
        $uriVariables = [];
        $context = [];

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($user)
        ;

        $this->collectionProvider->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn($watchFiles)
        ;

        $result = $this->provider->provide($operation, $uriVariables, $context);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testProvideCollectionWithNoAuthenticatedUser(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile1 = new WatchFile('Test WatchFile 1', 'Test objective 1', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile1, 'watchfile1');

        $watchFile2 = new WatchFile('Test WatchFile 2', 'Test objective 2', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile2, 'watchfile2');

        $watchFiles = [$watchFile1, $watchFile2];

        $operation = new GetCollection();
        $uriVariables = [];
        $context = [];

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn(null)
        ;

        $this->collectionProvider->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn($watchFiles)
        ;

        $result = $this->provider->provide($operation, $uriVariables, $context);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testProvideReturnsNullWhenProviderReturnsNull(): void
    {
        $operation = new GetCollection();
        $uriVariables = [];
        $context = [];

        $this->collectionProvider->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn(null)
        ;

        $result = $this->provider->provide($operation, $uriVariables, $context);

        $this->assertNull($result);
    }
}
