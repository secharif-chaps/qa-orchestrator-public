<?php

namespace App\Application\WatchFile\Share;

use App\Application\SyncActionInterface;

class RemoveShareWatchFileAction implements SyncActionInterface
{
    public function __construct(
        public readonly string $watchFileId,
        public readonly string $watchFileUserId,
        public readonly string $removedByUserId,
        public readonly \DateTimeImmutable $removedAt = new \DateTimeImmutable(),
    ) {
    }
}
