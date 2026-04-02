<?php

declare(strict_types=1);

namespace App\Application\Chat;

readonly class ErrorModelMessageAction
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $error,
        public ?string $messageId,
        public ?string $conversationId,
        public string $watchFileId,
        public array $context = [],
        public \DateTimeImmutable $errorTime = new \DateTimeImmutable(),
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
    }
}
