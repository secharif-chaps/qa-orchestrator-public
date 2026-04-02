<?php

declare(strict_types=1);

namespace App\Domain\WatchFile\Event;

use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;

class WatchFileStatusChangedEvent implements WatchFileRelatedEventInterface
{
    public function __construct(
        public readonly WatchFile $watchFile,
        public readonly User $user,
        public readonly WatchFileStatus $oldStatus,
        public readonly WatchFileStatus $status,
    ) {
    }

    public function getWatchFile(): WatchFile
    {
        return $this->watchFile;
    }
}
