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
class WatchFileProvider implements ProviderInterface
{
    /**
     * @var ProviderInterface<WatchFile>
     */
    private readonly ProviderInterface $itemProvider;

    /**
     * @param ProviderInterface<WatchFile> $itemProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        ProviderInterface $itemProvider,
        private readonly Security $security,
        private readonly EntityEnrichmentOrchestratorInterface $watchFileEnricher,
    ) {
        /** @var ProviderInterface<WatchFile> $itemProvider */
        $this->itemProvider = $itemProvider;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?WatchFile
    {
        /** @var WatchFile|null $result */
        $result = $this->itemProvider->provide($operation, $uriVariables, $context);

        if (null === $result) {
            return null;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $result;
        }

        return $this->watchFileEnricher->enrich($result, [
            'user' => $user,
        ]);
    }
}
