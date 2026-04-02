<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

final class DefaultScoringStrategy implements ScoringStrategyInterface
{
    /**
     * @param array<string, Signal> $signals
     */
    public function computeScore(array $signals, QualityConfig $config): ScoringResult
    {
        $categoryScores = $this->computeCategoryScores($signals);
        $overallScore = empty($categoryScores) ? 0.0 : min($categoryScores);
        $decision = $this->determineDecision($overallScore, $config);

        return new ScoringResult(
            overallScore: $overallScore,
            categoryScores: $categoryScores,
            decision: $decision,
        );
    }

    /**
     * @param array<string, Signal> $signals
     *
     * @return array<string, float>
     */
    private function computeCategoryScores(array $signals): array
    {
        /** @var array<string, array{totalScore: float, totalWeight: float}> $categories */
        $categories = [];

        foreach ($signals as $signal) {
            $cat = $signal->category->value;

            if (!isset($categories[$cat])) {
                $categories[$cat] = [
                    'totalScore' => 0.0,
                    'totalWeight' => 0.0,
                ];
            }

            $categories[$cat]['totalScore'] += $signal->contribution();
            $categories[$cat]['totalWeight'] += $signal->weight;
        }

        $result = [];
        foreach ($categories as $cat => $data) {
            $result[$cat] = $data['totalWeight'] > 0
                ? $data['totalScore'] / $data['totalWeight']
                : 0.0;
        }

        return $result;
    }

    private function determineDecision(float $score, QualityConfig $config): QualityDecision
    {
        return match (true) {
            $score >= $config->autoAcceptThreshold => QualityDecision::ACCEPTED,
            $score < $config->autoRejectThreshold => QualityDecision::LOW_QUALITY,
            default => QualityDecision::REVIEW,
        };
    }
}
