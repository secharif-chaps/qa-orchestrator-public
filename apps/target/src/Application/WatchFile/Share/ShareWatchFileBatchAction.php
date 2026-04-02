<?php

namespace App\Application\WatchFile\Share;

use App\Application\SyncActionInterface;

class ShareWatchFileBatchAction implements SyncActionInterface
{
    public function __construct(
        /** @var list<ShareWatchFileAction> */
        public readonly array $shareWatchFileActions,
        public readonly string $sharedByUserId,
        public readonly \DateTimeImmutable $sharedAt = new \DateTimeImmutable(),
    ) {
    }
}
