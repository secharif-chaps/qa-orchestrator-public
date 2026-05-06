<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Shared\EntityEnrichmentOrchestratorInterface;
use App\Domain\WatchFile\WatchFileActor;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<WatchFileActor>
 */
readonly class WatchFileActorProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<WatchFileActor> $apiPlatformProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $apiPlatformProvider,
        private WatchFileGatewayInterface $watchFileGateway,
        private Security $security,
        private EntityEnrichmentOrchestratorInterface $entityEnrichmentOrchestrator,
    ) {
    }

    /**
     * @return iterable<WatchFileActor>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?iterable
    {
        $watchFileId = null;

        if (isset($uriVariables['watchFileId']) && \is_string($uriVariables['watchFileId'])) {
            $watchFileId = $uriVariables['watchFileId'];
            // Check if the watchfile exists, throws an exception otherwise
            $watchFile = $this->watchFileGateway->get($watchFileId);
            if (!$this->security->isGranted(WatchFileVoter::VIEW, $watchFile)) {
                throw new AccessDeniedHttpException('You do not have access to this watchfile.');
            }
        }

        /** @var iterable<WatchFileActor>|null $result */
        $result = $this->apiPlatformProvider->provide($operation, $uriVariables, $context);

        if (null === $result || null === $watchFileId) {
            return $result;
        }

        return $this->entityEnrichmentOrchestrator->enrichCollection($result, [
            'watchFileId' => $watchFileId,
        ]);
    }
}
