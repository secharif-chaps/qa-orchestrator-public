<?php

declare(strict_types=1);

namespace App\Domain\Actor;

use App\Domain\User\User;
use App\Domain\WatchFile\Event\WatchFileRelatedEventInterface;
use App\Domain\WatchFile\WatchFile;
use Symfony\Contracts\EventDispatcher\Event;

class ActorStatusChangedEvent extends Event implements WatchFileRelatedEventInterface
{
    public function __construct(
        public readonly Actor $actor,
        public readonly WatchFile $watchFile,
        public readonly User $user,
        public readonly ActorStatus $status,
        public readonly ActorStatus $oldStatus,
    ) {
    }

    public function getWatchFile(): WatchFile
    {
        return $this->watchFile;
    }
}
