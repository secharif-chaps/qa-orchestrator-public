<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use App\Application\SyncActionInterface;
use App\Domain\WatchFile\WatchFile;

readonly class CalculateWatchFileChangesAction implements SyncActionInterface
{
    public function __construct(
        public WatchFile $originalData,
        public WatchFile $updatedData,
    ) {
    }
}
