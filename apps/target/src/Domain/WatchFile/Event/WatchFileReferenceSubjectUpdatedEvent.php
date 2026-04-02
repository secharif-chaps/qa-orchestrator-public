<?php

namespace App\Domain\WatchFile\Event;

use App\Domain\Shared\TranslatedText;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;

readonly class WatchFileReferenceSubjectUpdatedEvent implements WatchFileRelatedEventInterface
{
    /**
     * @param array<string, mixed>|null $context
     */
    public function __construct(
        public WatchFile $watchFile,
        public User $user,
        public ?TranslatedText $oldReferenceSubject,
        public TranslatedText $newReferenceSubject,
        public ?array $context = null,
    ) {
    }

    public function getWatchFile(): WatchFile
    {
        return $this->watchFile;
    }
}
