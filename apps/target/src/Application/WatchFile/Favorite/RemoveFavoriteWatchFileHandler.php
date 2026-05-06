<?php

namespace App\Application\WatchFile\Favorite;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\User\UserFavoriteWatchFileGatewayInterface;
use App\Domain\User\UserFavoriteWatchFileNotFoundException;
use App\Domain\User\UserGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RemoveFavoriteWatchFileHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private readonly UserGatewayInterface $userGateway,
        private readonly UserFavoriteWatchFileGatewayInterface $userFavoriteWatchFileGateway,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(RemoveFavoriteWatchFileAction $action): bool
    {
        $watchFile = $this->getWatchFile($action->watchFileId);
        $user = $this->userGateway->get($action->userId);

        try {
            $favoriteWatchFile = $this->userFavoriteWatchFileGateway->getByUserAndWatchFile($user, $watchFile);

            $this->userFavoriteWatchFileGateway->remove($favoriteWatchFile);
            $this->logger?->info(
                'User favorite watchfile removed for user {userId} and watchfile {watchFileId}',
                [
                    'userId' => $action->userId,
                    'watchFileId' => $action->watchFileId,
                ]
            );

            return true;
        } catch (UserFavoriteWatchFileNotFoundException) {
            $this->logger?->debug(
                'User favorite watchfile not found for user {userId} and watchfile {watchFileId}',
                [
                    'userId' => $action->userId,
                    'watchFileId' => $action->watchFileId,
                ]
            );

            return false;
        }
    }
}
