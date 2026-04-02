<?php

declare(strict_types=1);

namespace App\Application\DocumentQuality\Event;

final readonly class DocumentQualityProcessedEvent
{
    public function __construct(
        public string $documentId,
        public string $reportId,
        public ?float $overallScore,
        public string $decision,
    ) {
    }
}
