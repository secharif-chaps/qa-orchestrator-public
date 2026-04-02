<?php

declare(strict_types=1);

namespace App\Domain\WatchFile\Event;

use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorType;
use App\Domain\Shared\TranslatedText;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;

/**
 * Event dispatched when an actor is added to a WatchFile.
 */
readonly class ActorAddedEvent implements WatchFileRelatedEventInterface
{
    public function __construct(
        public WatchFile $watchFile,
        public Actor $actor,
        public User $addedBy,
        public ActorType $actorType,
        public ?TranslatedText $explanation = null,
        public ?float $score = null,
    ) {
    }

    public function getWatchFile(): WatchFile
    {
        return $this->watchFile;
    }
}
