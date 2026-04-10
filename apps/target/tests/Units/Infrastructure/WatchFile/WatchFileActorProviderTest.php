<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Actor\Actor;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileActor;
use App\Infrastructure\Shared\EntityEnricherLocator;
use App\Infrastructure\Shared\EntityEnrichmentOrchestrator;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\Infrastructure\WatchFile\WatchFileActorProvider;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class WatchFileActorProviderTest extends TestCase
{
    use EntityUtilsTrait;
    private Security $security;
    private NullWatchFileGateway $watchFileGateway;

    /**
     * @var ProviderInterface<WatchFileActor>&Stub
     */
    private ProviderInterface&Stub $apiPlatformProvider;
    private WatchFileActorProvider $provider;

    protected function setUp(): void
    {
        $this->security = $this->createStub(Security::class);
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->apiPlatformProvider = $this->createStub(ProviderInterface::class);
        $this->buildProvider();
    }

    private function buildProvider(): void
    {
        $entityEnrichmentOrchestrator = new EntityEnrichmentOrchestrator(new EntityEnricherLocator([]));
        $this->provider = new WatchFileActorProvider(
            $this->apiPlatformProvider,
            $this->watchFileGateway,
            $this->security,
            $entityEnrichmentOrchestrator,
        );
    }

    public function testProvideDelegatesToApiPlatformProviderAndChecksWatchFile(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $apiPlatformProviderMock = $this->createMock(ProviderInterface::class);
        $this->apiPlatformProvider = $apiPlatformProviderMock;
        $this->buildProvider();
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('test-actor', new Organisation('Test Org', 'test-org-id'));
        $operation = $this->createStub(Operation::class);
        $uriVariables = [
            'watchFileId' => 'watch_file_id',
        ];
        $context = [];
        $expectedResult = [new WatchFileActor($actor, $watchFile)];

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $apiPlatformProviderMock->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn($expectedResult);

        $result = $this->provider->provide($operation, $uriVariables, $context);

        $this->assertSame($expectedResult, $result);
    }

    public function testProvideThrowsWatchFileNotFoundExceptionIfWatchFileDoesNotExist(): void
    {
        $operation = $this->createStub(Operation::class);
        $uriVariables = [
            'watchFileId' => 'non-existent-watchfile-id',
        ];
        $context = [];

        $this->expectException(\App\Domain\WatchFile\Exception\WatchFileNotFoundException::class);
        $this->provider->provide($operation, $uriVariables, $context);
    }
}
