<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Actor;

use App\Domain\Actor\ActorType;
use App\Domain\Shared\TranslatedText;

readonly class AddActorAction
{
    public function __construct(
        public string $watchFileId,
        public string $name,
        public ActorType $type,
        public TranslatedText $explanation,
        public ?string $primaryDomain,
        public ?float $score = null,
        public ?string $messageId = null,
    ) {
    }
}
