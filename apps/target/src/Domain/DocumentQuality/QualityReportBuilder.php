<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

use App\Domain\Document\Pipeline\DocumentPipelineContext;

/**
 * Builds a {@see QualityReport} from a {@see DocumentPipelineContext}.
 *
 * The pipeline itself is neutral and emits a context full of {@see Signal}s.
 * The bridge between that neutral context and the quality domain artefact
 * lives here:
 *
 * - if the pipeline halted (a processor decided to stop), the report is
 *   {@see QualityDecision::REJECTED} with the halt reason as decision reason;
 * - otherwise, signals are fed to the configured {@see ScoringStrategyInterface}
 *   and the resulting overall score / category scores / decision are stored
 *   on the report.
 */
readonly class QualityReportBuilder
{
    public function __construct(
        private ScoringStrategyInterface $scoringStrategy,
    ) {
    }

    public function build(DocumentPipelineContext $context, QualityConfig $qualityConfig): QualityReport
    {
        if ($context->isHalted) {
            return new QualityReport(
                documentId: $context->document->getId(),
                overallScore: null,
                categoryScores: [],
                signals: $context->signals,
                decision: QualityDecision::REJECTED,
                computedAt: new \DateTimeImmutable(),
                decisionReason: $context->haltReason,
            );
        }

        $scoring = $this->scoringStrategy->computeScore($context->signals, $qualityConfig);

        return new QualityReport(
            documentId: $context->document->getId(),
            overallScore: $scoring->overallScore,
            categoryScores: $scoring->categoryScores,
            signals: $context->signals,
            decision: $scoring->decision,
            computedAt: new \DateTimeImmutable(),
        );
    }
}
