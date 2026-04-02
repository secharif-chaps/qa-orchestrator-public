<?php

declare(strict_types=1);

namespace App\Application\Document;

readonly class UpdateDocumentEventsAction
{
    /**
     * @param array<int, array<string, mixed>> $events
     */
    public function __construct(
        public string $documentId,
        public string $watchFileId,
        public array $events = [],
        public ?string $error = null,
    ) {
    }

    public function hasEvents(): bool
    {
        return !empty($this->events);
    }

    public function hasFailed(): bool
    {
        return null !== $this->error;
    }
}
