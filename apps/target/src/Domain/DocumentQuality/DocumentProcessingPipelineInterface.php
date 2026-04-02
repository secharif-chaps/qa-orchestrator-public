<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

use App\Domain\Document\Document;
use App\Domain\WatchFile\WatchFile;

interface DocumentProcessingPipelineInterface
{
    public function process(Document $document, WatchFile $watchFile): QualityReport;
}
