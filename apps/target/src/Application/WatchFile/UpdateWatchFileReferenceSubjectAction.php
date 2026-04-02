<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use App\Domain\Shared\TranslatedText;
use App\Domain\User\User;
use App\Domain\WatchFile\Event\WatchFileReferenceSubjectUpdatedEvent;
use App\Domain\WatchFile\WatchFile;

readonly class UpdateWatchFileReferenceSubjectAction
{
    public function __construct(
        public string $watchFileId,
        public TranslatedText $referenceSubject,
        public ?string $referenceSubjectLlm = null,
        public ?string $messageId = null,
    ) {
    }

    public function toEvent(
        WatchFile $watchFile,
        User $user,
        ?TranslatedText $oldReferenceSubject,
    ): WatchFileReferenceSubjectUpdatedEvent {
        return new WatchFileReferenceSubjectUpdatedEvent(
            $watchFile,
            $user,
            $oldReferenceSubject,
            $this->referenceSubject,
            [
                'messageId' => $this->messageId,
            ],
        );
    }
}
