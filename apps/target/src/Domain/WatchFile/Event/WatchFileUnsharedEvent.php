<?php

declare(strict_types=1);

namespace App\Domain\WatchFile\Event;

use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileUser;

class WatchFileUnsharedEvent
{
    public function __construct(
        public readonly WatchFile $watchFile,
        public readonly WatchFileUser $watchFileUser,
        public readonly User $removedBy,
    ) {
    }
}
