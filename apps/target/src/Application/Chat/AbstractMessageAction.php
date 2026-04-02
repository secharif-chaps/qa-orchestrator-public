<?php

declare(strict_types=1);

namespace App\Application\Chat;

abstract readonly class AbstractMessageAction
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $conversationId,
        public string $message,
        public array $context = [],
    ) {
    }

    public function getTrimmedMessage(): string
    {
        return trim($this->message);
    }

    public function isValidMessage(): bool
    {
        return !empty($this->getTrimmedMessage());
    }
}
