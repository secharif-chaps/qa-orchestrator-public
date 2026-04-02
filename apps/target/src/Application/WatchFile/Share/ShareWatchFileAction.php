<?php

namespace App\Application\WatchFile\Share;

use App\Application\SyncActionInterface;
use App\Domain\WatchFile\WatchFileUserRole;

class ShareWatchFileAction implements SyncActionInterface
{
    public function __construct(
        public readonly string $watchFileId,
        public readonly string $userId,
        public readonly WatchFileUserRole $role,
    ) {
    }
}
