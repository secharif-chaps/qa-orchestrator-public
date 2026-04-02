<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use App\Application\SyncActionInterface;
use App\Domain\WatchFile\WatchFileStatus;

final readonly class ChangeWatchFileStatusAction implements SyncActionInterface
{
    public function __construct(
        public string $watchFileId,
        public WatchFileStatus $status,
        public ?string $messageId = null,
    ) {
    }
}
