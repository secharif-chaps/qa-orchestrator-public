<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Source\Source;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<Source>
 */
class WatchFileSourceProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<Source> $apiPlatformProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $apiPlatformProvider,
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly Security $security,
    ) {
    }

    /**
     * @return iterable<Source>|Source|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable|object|null
    {
        if (isset($uriVariables['id']) && \is_string($uriVariables['id'])) {
            $watchFile = $this->watchFileGateway->get($uriVariables['id']);
            if (!$this->security->isGranted(WatchFileVoter::VIEW, $watchFile)) {
                throw new AccessDeniedException('You do not have access to this watch file.');
            }
        }

        // Internal source types (manual) are excluded at SQL level
        // by InternalSourceTypeFilterExtension (Doctrine ORM extension)
        return $this->apiPlatformProvider->provide($operation, $uriVariables, $context);
    }
}
