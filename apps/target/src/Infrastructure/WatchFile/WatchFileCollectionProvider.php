<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Shared\EntityEnrichmentOrchestratorInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provider that wraps the existing API Platform provider and adds the isFavorite property
 * to WatchFile entities based on the current user context.
 *
 * @implements ProviderInterface<WatchFile>
 */
class WatchFileCollectionProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<WatchFile> $collectionProvider
     */
    public function __construct(
        /** @var ProviderInterface<WatchFile> $collectionProvider */
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $collectionProvider,
        private readonly Security $security,
        private readonly EntityEnrichmentOrchestratorInterface $watchFileEnricher,
    ) {
    }

    /**
     * @return iterable<WatchFile>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?iterable
    {
        /** @var iterable<WatchFile>|null $result */
        $result = $this->collectionProvider->provide($operation, $uriVariables, $context);

        if (null === $result) {
            return null;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $result;
        }

        return $this->watchFileEnricher->enrichCollection($result, [
            'user' => $user,
        ]);
    }
}
