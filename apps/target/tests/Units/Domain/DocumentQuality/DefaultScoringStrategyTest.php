<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\DocumentQuality;

use App\Domain\DocumentQuality\DefaultScoringStrategy;
use App\Domain\DocumentQuality\QualityConfig;
use App\Domain\DocumentQuality\QualityDecision;
use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultScoringStrategy::class)]
class DefaultScoringStrategyTest extends TestCase
{
    private DefaultScoringStrategy $strategy;
    private QualityConfig $config;

    protected function setUp(): void
    {
        $this->strategy = new DefaultScoringStrategy();
        $this->config = new QualityConfig(autoAcceptThreshold: 0.7, autoRejectThreshold: 0.2);
    }

    public function testEmptySignalsReturnsScoreZeroAndLowQuality(): void
    {
        $result = $this->strategy->computeScore([], $this->config);

        self::assertSame(0.0, $result->overallScore);
        self::assertSame([], $result->categoryScores);
        self::assertSame(QualityDecision::LOW_QUALITY, $result->decision);
    }

    public function testSingleSignalOverallScoreEqualsItsWeightedValue(): void
    {
        $signals = [
            'https' => new Signal(value: 0.80, weight: 0.5, category: SignalCategory::INFRASTRUCTURE_TRUST),
        ];

        $result = $this->strategy->computeScore($signals, $this->config);

        // single signal → category avg = value itself, overall = that category
        self::assertSame(0.80, $result->overallScore);
        self::assertSame(0.80, $result->categoryScores['infrastructure_trust']);
    }

    public function testCategoryScoreIsWeightedAverage(): void
    {
        // Two signals in same category:
        //   word_count: value=0.80, weight=0.6  → contribution = 0.48
        //   content_ratio: value=0.50, weight=0.7  → contribution = 0.35
        //   weighted avg = (0.48 + 0.35) / (0.6 + 0.7) = 0.83 / 1.3 ≈ 0.638...
        $signals = [
            'word_count' => new Signal(value: 0.80, weight: 0.6, category: SignalCategory::CONTENT_QUALITY),
            'content_ratio' => new Signal(value: 0.50, weight: 0.7, category: SignalCategory::CONTENT_QUALITY),
        ];

        $result = $this->strategy->computeScore($signals, $this->config);

        $expected = (0.80 * 0.6 + 0.50 * 0.7) / (0.6 + 0.7);
        self::assertEqualsWithDelta($expected, $result->categoryScores['content_quality'], 0.0001);
        self::assertSame($result->categoryScores['content_quality'], $result->overallScore);
    }

    public function testOverallScoreIsMinimumAcrossCategories(): void
    {
        // content_quality: value=0.90, weight=1.0  → avg = 0.90
        // infrastructure_trust: value=0.35, weight=0.5  → avg = 0.35
        // overall = min(0.90, 0.35) = 0.35
        $signals = [
            'word_count' => new Signal(value: 0.90, weight: 1.0, category: SignalCategory::CONTENT_QUALITY),
            'https' => new Signal(value: 0.35, weight: 0.5, category: SignalCategory::INFRASTRUCTURE_TRUST),
        ];

        $result = $this->strategy->computeScore($signals, $this->config);

        self::assertSame(0.35, $result->overallScore);
        self::assertSame(0.90, $result->categoryScores['content_quality']);
        self::assertSame(0.35, $result->categoryScores['infrastructure_trust']);
    }

    public function testOverallScoreIsMinNotAverageOfCategories(): void
    {
        // Three categories with very different scores — min wins, not mean
        $signals = [
            'a' => new Signal(value: 0.90, weight: 1.0, category: SignalCategory::CONTENT_QUALITY),
            'b' => new Signal(value: 0.80, weight: 1.0, category: SignalCategory::METADATA),
            'c' => new Signal(value: 0.10, weight: 1.0, category: SignalCategory::INFRASTRUCTURE_TRUST),
        ];

        $result = $this->strategy->computeScore($signals, $this->config);

        self::assertSame(0.10, $result->overallScore);
    }

    public function testCategoryScoresKeyedByCategoryStringValue(): void
    {
        $signals = [
            'pub_date' => new Signal(value: 0.75, weight: 0.5, category: SignalCategory::METADATA),
            'https' => new Signal(value: 0.80, weight: 0.5, category: SignalCategory::INFRASTRUCTURE_TRUST),
        ];

        $result = $this->strategy->computeScore($signals, $this->config);

        self::assertArrayHasKey('metadata', $result->categoryScores);
        self::assertArrayHasKey('infrastructure_trust', $result->categoryScores);
    }

    public function testDecisionIsAcceptedWhenScoreAboveThreshold(): void
    {
        // overall = 0.80 ≥ autoAcceptThreshold(0.7)
        $signals = [
            'https' => new Signal(value: 0.80, weight: 1.0, category: SignalCategory::INFRASTRUCTURE_TRUST),
        ];

        $result = $this->strategy->computeScore($signals, $this->config);

        self::assertSame(QualityDecision::ACCEPTED, $result->decision);
    }

    public function testDecisionIsReviewWhenScoreBetweenThresholds(): void
    {
        // overall = 0.50 → between 0.2 and 0.7
        $signals = [
            'https' => new Signal(value: 0.50, weight: 1.0, category: SignalCategory::INFRASTRUCTURE_TRUST),
        ];

        $result = $this->strategy->computeScore($signals, $this->config);

        self::assertSame(QualityDecision::REVIEW, $result->decision);
    }

    public function testDecisionIsLowQualityWhenScoreBelowRejectThreshold(): void
    {
        // overall = 0.10 < autoRejectThreshold(0.2)
        $signals = [
            'https' => new Signal(value: 0.10, weight: 1.0, category: SignalCategory::INFRASTRUCTURE_TRUST),
        ];

        $result = $this->strategy->computeScore($signals, $this->config);

        self::assertSame(QualityDecision::LOW_QUALITY, $result->decision);
    }

    public function testDecisionAtExactAcceptThresholdIsAccepted(): void
    {
        $signals = [
            'https' => new Signal(value: 0.70, weight: 1.0, category: SignalCategory::INFRASTRUCTURE_TRUST),
        ];

        $result = $this->strategy->computeScore($signals, $this->config);

        self::assertSame(QualityDecision::ACCEPTED, $result->decision);
    }

    public function testDecisionJustBelowRejectThresholdIsLowQuality(): void
    {
        $signals = [
            'https' => new Signal(value: 0.19, weight: 1.0, category: SignalCategory::INFRASTRUCTURE_TRUST),
        ];

        $result = $this->strategy->computeScore($signals, $this->config);

        self::assertSame(QualityDecision::LOW_QUALITY, $result->decision);
    }

    public function testCustomThresholdsAreRespected(): void
    {
        $strictConfig = new QualityConfig(autoAcceptThreshold: 0.9, autoRejectThreshold: 0.5);

        $signals = [
            'https' => new Signal(value: 0.70, weight: 1.0, category: SignalCategory::INFRASTRUCTURE_TRUST),
        ];

        $result = $this->strategy->computeScore($signals, $strictConfig);

        // 0.70 < 0.9 and >= 0.5 → review (not accepted with strict config)
        self::assertSame(QualityDecision::REVIEW, $result->decision);
    }

    public function testRealWorldHttpDocumentScenario(): void
    {
        // Mirrors the fixture documents: HTTP URL caps the overall score at 0.35
        $signals = [
            'https' => new Signal(value: 0.35, weight: 0.5, category: SignalCategory::INFRASTRUCTURE_TRUST),
            'word_count' => new Signal(value: 0.60, weight: 0.6, category: SignalCategory::CONTENT_QUALITY),
            'content_ratio' => new Signal(value: 0.50, weight: 0.7, category: SignalCategory::CONTENT_QUALITY),
            'publication_date' => new Signal(value: 0.80, weight: 0.5, category: SignalCategory::METADATA),
        ];

        $result = $this->strategy->computeScore($signals, $this->config);

        self::assertSame(0.35, $result->overallScore);
        self::assertSame(QualityDecision::REVIEW, $result->decision);
    }

    public function testRealWorldHttpsDocumentScenario(): void
    {
        // HTTPS document — infrastructure_trust no longer the bottleneck
        $signals = [
            'https' => new Signal(value: 0.80, weight: 0.5, category: SignalCategory::INFRASTRUCTURE_TRUST),
            'word_count' => new Signal(value: 0.80, weight: 0.6, category: SignalCategory::CONTENT_QUALITY),
            'content_ratio' => new Signal(value: 0.80, weight: 0.7, category: SignalCategory::CONTENT_QUALITY),
            'publication_date' => new Signal(value: 0.80, weight: 0.5, category: SignalCategory::METADATA),
        ];

        $result = $this->strategy->computeScore($signals, $this->config);

        self::assertSame(0.80, $result->overallScore);
        self::assertSame(QualityDecision::ACCEPTED, $result->decision);
    }
}
