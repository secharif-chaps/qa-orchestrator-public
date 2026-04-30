<?php

declare(strict_types=1);

namespace App\Application\Document\Pipeline;

/**
 * Bus message that triggers the asynchronous post-save pipeline run for a
 * persisted document. Dispatched by {@see \App\Application\Document\IngestDocumentHandler}
 * after a successful save (with `DispatchAfterCurrentBusStamp` so the message
 * is only handed off once the ingest transaction has committed).
 *
 * The receiving {@see RunPostSavePipelineHandler} executes the post-save
 * pipeline (currently scoring; will host fuzzy deduplication too) and produces
 * the resulting domain artefacts (a {@see \App\Domain\DocumentQuality\QualityReport}
 * for now).
 */
readonly class RunPostSavePipelineAction
{
    public function __construct(
        public string $documentId,
    ) {
    }
}
