<?php

namespace App\Application\WatchFile\Favorite;

use App\Application\SyncActionInterface;

readonly class AddFavoriteWatchFileAction implements SyncActionInterface
{
    public function __construct(
        public string $watchFileId,
        public string $userId,
    ) {
    }
}
