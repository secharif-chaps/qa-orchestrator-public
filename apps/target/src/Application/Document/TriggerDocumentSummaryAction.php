<?php

declare(strict_types=1);

namespace App\Application\Document;

final readonly class TriggerDocumentSummaryAction
{
    public function __construct(
        public string $documentId,
    ) {
    }
}
