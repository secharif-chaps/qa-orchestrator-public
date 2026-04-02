<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\Actor;

use ApiPlatform\State\ProviderInterface;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorGatewayInterface;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\WatchFile\ActorSourceProvider;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\Tests\Units\Infrastructure\Actor\NullActorGateway;
use App\Tests\Units\Infrastructure\Shared\NullResourceMetadataCollectionFactory;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\MockHelpersTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ActorSourceProviderTest extends TestCase
{
    use MockHelpersTrait;
    protected ActorSourceProvider $provider;
    protected ActorGatewayInterface $actorGateway;
    protected SourceGatewayInterface $sourceGateway;
    protected WatchFileGatewayInterface $watchFileGateway;

    /** @var Security&Stub */
    protected Security $security;

    /** @var ProviderInterface<Source> */
    protected ProviderInterface $apiPlatformProvider;
    protected NullResourceMetadataCollectionFactory $resourceMetadataCollectionFactory;

    protected function setUp(): void
    {
        $this->actorGateway = new NullActorGateway();
        $this->sourceGateway = new NullSourceGateway();
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->security = $this->createStub(Security::class);
        $this->apiPlatformProvider = $this->createStub(ProviderInterface::class);
        $this->resourceMetadataCollectionFactory = new NullResourceMetadataCollectionFactory();
        $this->buildProvider();
    }

    private function buildProvider(): void
    {
        $this->provider = new ActorSourceProvider(
            $this->apiPlatformProvider,
            $this->actorGateway,
            $this->security,
            $this->watchFileGateway,
            $this->resourceMetadataCollectionFactory
        );
    }

    public function testProvideSuccessfully(): void
    {
        $apiPlatformProvider = $this->createMockWithExpectations(ProviderInterface::class);
        $securityMock = $this->createMockWithExpectations(Security::class);
        $this->apiPlatformProvider = $apiPlatformProvider;
        $this->security = $securityMock;
        $this->buildProvider();
        // Arrange
        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source1 = new Source(
            'Test Source 1',
            TranslatedText::fromArray([
                'fr' => 'Description FR',
                'en' => 'Description EN',
            ]),
            SourceType::WEBSITE,
            'https://example1.com',
            'example1.com',
            TranslatedText::fromArray([
                'fr' => 'Relevance FR',
                'en' => 'Relevance EN',
            ]),
            $actor,
            $watchFile
        );
        $source2 = new Source(
            'Test Source 2',
            TranslatedText::fromArray([
                'fr' => 'Description FR',
                'en' => 'Description EN',
            ]),
            SourceType::WEBSITE,
            'https://example2.com',
            'example2.com',
            TranslatedText::fromArray([
                'fr' => 'Relevance FR',
                'en' => 'Relevance EN',
            ]),
            $actor,
            $watchFile
        );

        // Save entities to null gateways
        $this->watchFileGateway->save($watchFile);
        $this->actorGateway->save($actor);
        $this->sourceGateway->save($source1);
        $this->sourceGateway->save($source2);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $apiPlatformProvider
            ->expects($this->once())
            ->method('provide')
            ->willReturn([$source1, $source2]);

        // Act
        $result = $this->provider->provide(
            $this->createStub(\ApiPlatform\Metadata\Operation::class),
            [
                'watchFileId' => $watchFile->getId(),
                'actorId' => $actor->getId(),
            ]
        );

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContains($source1, $result);
        $this->assertContains($source2, $result);
    }

    public function testProvideSuccessfullyWithNoSources(): void
    {
        $apiPlatformProvider = $this->createMockWithExpectations(ProviderInterface::class);
        $securityMock = $this->createMockWithExpectations(Security::class);
        $this->apiPlatformProvider = $apiPlatformProvider;
        $this->security = $securityMock;
        $this->buildProvider();
        // Arrange
        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));

        // Save entities to null gateways
        $this->watchFileGateway->save($watchFile);
        $this->actorGateway->save($actor);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $apiPlatformProvider
            ->expects($this->once())
            ->method('provide')
            ->willReturn([]);

        // Act
        $result = $this->provider->provide(
            $this->createStub(\ApiPlatform\Metadata\Operation::class),
            [
                'watchFileId' => $watchFile->getId(),
                'actorId' => $actor->getId(),
            ]
        );

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testProvideWithMissingWatchFileId(): void
    {
        // Act & Assert
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Watch file ID is required');

        $this->provider->provide(
            $this->createStub(\ApiPlatform\Metadata\Operation::class),
            [
                'actorId' => 'actor-id',
            ]
        );
    }

    public function testProvideWithMissingActorId(): void
    {
        // Act & Assert
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Actor ID is required');

        $this->provider->provide(
            $this->createStub(\ApiPlatform\Metadata\Operation::class),
            [
                'watchFileId' => 'watch-file-id',
            ]
        );
    }

    public function testProvideWithAccessDenied(): void
    {
        $securityMock = $this->createMockWithExpectations(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        // Arrange
        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));

        // Save entities to null gateways
        $this->watchFileGateway->save($watchFile);
        $this->actorGateway->save($actor);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(false);

        // Act & Assert
        $this->expectException(AccessDeniedHttpException::class);

        $this->provider->provide(
            $this->createStub(\ApiPlatform\Metadata\Operation::class),
            [
                'watchFileId' => $watchFile->getId(),
                'actorId' => $actor->getId(),
            ]
        );
    }

    public function testProvideWithWatchFileNotFound(): void
    {
        $securityMock = $this->createMockWithExpectations(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        // Arrange
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->actorGateway->save($actor);

        $securityMock->expects($this->never())
            ->method('isGranted');

        // Act & Assert
        $this->expectException(\App\Domain\WatchFile\Exception\WatchFileNotFoundException::class);

        $this->provider->provide(
            $this->createStub(\ApiPlatform\Metadata\Operation::class),
            [
                'watchFileId' => 'non-existent-watch-file-id',
                'actorId' => $actor->getId(),
            ]
        );
    }

    public function testProvideWithActorNotFound(): void
    {
        $securityMock = $this->createMockWithExpectations(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        // Arrange
        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->watchFileGateway->save($watchFile);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        // Act & Assert
        $this->expectException(\App\Domain\Actor\ActorNotFoundException::class);

        $this->provider->provide(
            $this->createStub(\ApiPlatform\Metadata\Operation::class),
            [
                'watchFileId' => $watchFile->getId(),
                'actorId' => 'non-existent-actor-id',
            ]
        );
    }
}
