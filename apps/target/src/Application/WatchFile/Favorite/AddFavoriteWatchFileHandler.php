<?php

namespace App\Application\WatchFile\Favorite;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\User\UserFavoriteWatchFile;
use App\Domain\User\UserFavoriteWatchFileGatewayInterface;
use App\Domain\User\UserFavoriteWatchFileNotFoundException;
use App\Domain\User\UserGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class AddFavoriteWatchFileHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private readonly UserGatewayInterface $userGateway,
        private readonly UserFavoriteWatchFileGatewayInterface $userFavoriteWatchFileGateway,
        private readonly RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(AddFavoriteWatchFileAction $action): bool
    {
        $watchFile = $this->getWatchFile($action->watchFileId);
        $user = $this->userGateway->get($action->userId);

        try {
            $this->userFavoriteWatchFileGateway->getByUserAndWatchFile($user, $watchFile);

            // If we reach this point, the watchfile is already a favorite, so we do nothing.
            $this->logger?->debug(
                'WatchFile {watchFileId} is already a favorite for user {userId}.',
                [
                    'watchFileId' => $action->watchFileId,
                    'userId' => $action->userId,
                ]
            );

            return false;
        } catch (UserFavoriteWatchFileNotFoundException) {
            $watchFileFavorite = new UserFavoriteWatchFile($user, $watchFile);

            $this->userFavoriteWatchFileGateway->save($watchFileFavorite);
            $this->logger?->info(
                'Added watchfile {watchFileId} to favorites for user {userId}.',
                [
                    'watchFileId' => $action->watchFileId,
                    'userId' => $action->userId,
                ]
            );

            $this->realTimeUpdatePublisher->publishWatchFileUpdate($watchFile);

            return true;
        }
    }
}
