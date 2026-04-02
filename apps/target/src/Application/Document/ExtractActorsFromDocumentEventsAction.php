<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Application\SyncActionInterface;

readonly class ExtractActorsFromDocumentEventsAction implements SyncActionInterface
{
    /**
     * @param array<int, array<string, mixed>> $events
     */
    public function __construct(
        public string $documentId,
        public string $watchFileId,
        public array $events,
    ) {
    }
}
