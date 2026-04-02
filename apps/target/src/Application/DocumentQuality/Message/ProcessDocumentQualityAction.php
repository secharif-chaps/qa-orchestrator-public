<?php

declare(strict_types=1);

namespace App\Application\DocumentQuality\Message;

final readonly class ProcessDocumentQualityAction
{
    public function __construct(
        public string $documentId,
    ) {
    }
}
