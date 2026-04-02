<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

readonly class ScoringResult
{
    /**
     * @param array<string, float> $categoryScores
     */
    public function __construct(
        public float $overallScore,
        public array $categoryScores,
        public QualityDecision $decision,
    ) {
    }
}
