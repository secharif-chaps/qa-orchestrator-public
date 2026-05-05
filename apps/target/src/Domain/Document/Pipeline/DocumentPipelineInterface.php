<?php

declare(strict_types=1);

namespace App\Domain\Document\Pipeline;

use App\Domain\Document\Document;
use App\Domain\WatchFile\WatchFile;

/**
 * Generic, neutral pipeline that runs a tagged set of {@see DocumentProcessorInterface}
 * over a {@see Document} and returns the resulting {@see DocumentPipelineContext}.
 *
 * The pipeline itself produces no domain artefacts: callers are expected
 * to interpret the returned context (e.g. build a `QualityReport`, dispatch
 * a duplicate event, etc.). This keeps the pipeline reusable across
 * concerns (quality scoring, deduplication, future use cases).
 */
interface DocumentPipelineInterface
{
    /**
     * @param string|null $collectTaskId identity of the collect task that produced the document — required by the
     *                                   deduplication processor to record a `DuplicateAttempt` on a matched original
     * @param string|null $provider      provider name (apify, bakus, manual…) for the same trace
     */
    public function process(
        Document $document,
        WatchFile $watchFile,
        ?string $collectTaskId = null,
        ?string $provider = null,
    ): DocumentPipelineContext;
}
