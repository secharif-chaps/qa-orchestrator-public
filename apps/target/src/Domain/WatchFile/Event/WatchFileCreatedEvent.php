<?php

namespace App\Domain\WatchFile\Event;

use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;

class WatchFileCreatedEvent implements WatchFileRelatedEventInterface
{
    public function __construct(
        public readonly WatchFile $watchFile,
        public readonly User $user,
    ) {
    }

    public function getWatchFile(): WatchFile
    {
        return $this->watchFile;
    }
}
