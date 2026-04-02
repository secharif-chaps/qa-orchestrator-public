<?php

declare(strict_types=1);

namespace App\Application\Agent;

abstract class TriggerAgent
{
    /**
     * @param class-string         $responseType
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $name,
        public array $data,
        public string $responseType,
        public ?string $watchFileId = null,
        public ?string $userId = null,
        public \DateTime $triggeredAt = new \DateTime(),
    ) {
    }
}
