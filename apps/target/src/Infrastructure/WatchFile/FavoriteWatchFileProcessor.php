<?php

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\WatchFile\Favorite\AddFavoriteWatchFileAction;
use App\Application\WatchFile\Favorite\RemoveFavoriteWatchFileAction;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<WatchFile, void>
 */
class FavoriteWatchFileProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly Security $security,
        private readonly WatchFileGatewayInterface $watchFileGateway,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('User must be authenticated to favorite a watch file.');
        }

        $watchFileId = $uriVariables['id'] ?? null;
        if (!$watchFileId || !\is_string($watchFileId)) {
            throw new BadRequestHttpException('Watch file ID is required.');
        }
        $watchFile = $this->watchFileGateway->get($watchFileId);

        if (!$this->security->isGranted(WatchFileVoter::VIEW, $watchFile)) {
            throw new AccessDeniedHttpException('User must have right access to favorite a watch file.');
        }

        $userId = $user->getId();
        if (!$userId) {
            throw new BadRequestHttpException('User ID is required.');
        }

        if ($operation instanceof Delete) {
            $action = new RemoveFavoriteWatchFileAction($watchFile->getId(), $userId);
        } else {
            $action = new AddFavoriteWatchFileAction($watchFile->getId(), $userId);
        }

        $this->messageBus->dispatch($action);
    }
}
