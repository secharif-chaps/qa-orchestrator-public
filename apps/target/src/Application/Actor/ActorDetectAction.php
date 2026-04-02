<?php

declare(strict_types=1);

namespace App\Application\Actor;

class ActorDetectAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public array $data,
        public string $watchFileId,
    ) {
    }
}
