<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFileEvent;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileSecurity;
use App\Domain\WatchFileEvent\WatchFileEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<WatchFileEvent>
 */
readonly class WatchFileEventItemProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<WatchFileEvent> $itemProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.elasticsearch.state.item_provider')]
        private ProviderInterface $itemProvider,
        private WatchFileGatewayInterface $watchFileGateway,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?WatchFileEvent
    {
        $result = $this->itemProvider->provide($operation, $uriVariables, $context);

        if (!$result instanceof WatchFileEvent) {
            return null;
        }

        $esWatchFile = $result->getWatchFile();

        if (null === $esWatchFile) {
            throw new AccessDeniedException('You do not have permission to view this event.');
        }

        try {
            $watchFile = $this->watchFileGateway->get($esWatchFile->getId());
        } catch (WatchFileNotFoundException) {
            throw new NotFoundHttpException('The associated watchfile was not found.');
        }

        if (!$this->security->isGranted(WatchFileSecurity::VIEW, $watchFile)) {
            throw new AccessDeniedException('You do not have permission to view this event.');
        }

        return $result;
    }
}
