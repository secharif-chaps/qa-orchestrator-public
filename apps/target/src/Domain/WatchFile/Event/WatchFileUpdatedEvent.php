<?php

namespace App\Domain\WatchFile\Event;

use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;

readonly class WatchFileUpdatedEvent implements WatchFileRelatedEventInterface
{
    /**
     * @param array<string, array{old: mixed, new: mixed}> $changes
     * @param array<string, mixed>|null                    $context
     */
    public function __construct(
        public WatchFile $watchFile,
        public User $user,
        public array $changes,
        public ?array $context = null,
    ) {
    }

    public function getWatchFile(): WatchFile
    {
        return $this->watchFile;
    }
}
