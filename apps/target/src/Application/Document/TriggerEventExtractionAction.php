<?php

declare(strict_types=1);

namespace App\Application\Document;

final readonly class TriggerEventExtractionAction
{
    public function __construct(
        public string $documentId,
    ) {
    }
}
