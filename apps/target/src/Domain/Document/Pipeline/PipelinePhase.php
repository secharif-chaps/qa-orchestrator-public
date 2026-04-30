<?php

declare(strict_types=1);

namespace App\Domain\Document\Pipeline;

/**
 * Logical phases that group document-pipeline processors by intent.
 *
 * Phases are an *informative* concept: the runtime ordering is driven
 * by the integer priority of each `#[AsTaggedItem(priority: …)]`. By
 * convention, processors choose a priority inside their phase range so
 * the iterator yields ENRICHMENT first, then DEDUPLICATION, then SCORING.
 *
 * Pre-save pipeline runs ENRICHMENT and exact-match DEDUPLICATION (Stage 0).
 * Post-save pipeline runs SCORING and fuzzy DEDUPLICATION (Stage 1+).
 */
enum PipelinePhase: string
{
    case ENRICHMENT = 'enrichment';
    case DEDUPLICATION = 'deduplication';
    case SCORING = 'scoring';

    /**
     * @return array{int, int} [min, max] inclusive priority range
     */
    public function priorityRange(): array
    {
        return match ($this) {
            self::ENRICHMENT => [200, 299],
            self::DEDUPLICATION => [100, 199],
            self::SCORING => [1, 99],
        };
    }
}
