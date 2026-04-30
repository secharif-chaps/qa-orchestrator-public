<?php

declare(strict_types=1);

namespace App\Application\Document\Pipeline;

/**
 * Domain event dispatched by {@see RunPostSavePipelineHandler} once the
 * asynchronous post-save pipeline has finished running and its outputs
 * (currently a {@see \App\Domain\DocumentQuality\QualityReport}) have been
 * persisted.
 *
 * Listeners can react to this event to trigger downstream side effects
 * (e.g. AI validation only when quality is acceptable). They must remain
 * tolerant: the pipeline may have halted early and produced a `REJECTED`
 * decision with a `null` overall score.
 */
readonly class PostSavePipelineCompletedEvent
{
    public function __construct(
        public string $documentId,
        public string $reportId,
        public ?float $overallScore,
        public string $decision,
    ) {
    }
}
