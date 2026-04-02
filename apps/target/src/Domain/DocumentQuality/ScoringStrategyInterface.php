<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

interface ScoringStrategyInterface
{
    /**
     * @param array<string, Signal> $signals
     */
    public function computeScore(array $signals, QualityConfig $config): ScoringResult;
}
