<?php

declare(strict_types=1);

namespace App\Application\Document;

readonly class TriggerDocumentAiValidationAction
{
    public function __construct(
        public string $documentId,
    ) {
    }
}
