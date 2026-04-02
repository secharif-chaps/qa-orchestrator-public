<?php

namespace App\Application\WatchFile\Favorite;

use App\Application\SyncActionInterface;

readonly class RemoveFavoriteWatchFileAction implements SyncActionInterface
{
    public function __construct(
        public string $watchFileId,
        public string $userId,
    ) {
    }
}
