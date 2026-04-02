<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentQuality;

use App\Domain\Document\Document;
use App\Domain\DocumentQuality\DocumentProcessingPipelineInterface;
use App\Domain\DocumentQuality\DocumentProcessorInterface;
use App\Domain\DocumentQuality\ProcessingContext;
use App\Domain\DocumentQuality\QualityDecision;
use App\Domain\DocumentQuality\QualityReport;
use App\Domain\DocumentQuality\ScoringStrategyInterface;
use App\Domain\WatchFile\WatchFile;
use Psr\Log\LoggerInterface;

class DocumentProcessingPipeline implements DocumentProcessingPipelineInterface
{
    /**
     * @param iterable<DocumentProcessorInterface> $processors
     */
    public function __construct(
        private readonly iterable $processors,
        private readonly ScoringStrategyInterface $scoringStrategy,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(Document $document, WatchFile $watchFile): QualityReport
    {
        $context = new ProcessingContext($document, $watchFile);

        foreach ($this->processors as $processor) {
            if (!$processor->supports($context)) {
                $this->logger->debug('Processor skipped', [
                    'processor' => $processor::class,
                    'document_id' => $document->getId(),
                ]);
                continue;
            }

            $context = $processor->process($context);

            $this->logger->debug('Processor ran', [
                'processor' => $processor::class,
                'document_id' => $document->getId(),
                'signals' => array_keys($context->signals),
            ]);

            if ($context->hasEarlyDecision()) {
                $this->logger->info('Early decision triggered', [
                    'processor' => $processor::class,
                    'document_id' => $document->getId(),
                    'decision' => $context->earlyDecision?->value,
                    'reason' => $context->earlyDecisionReason,
                ]);
                break;
            }
        }

        return $this->buildReport($context, $watchFile);
    }

    private function buildReport(ProcessingContext $context, WatchFile $watchFile): QualityReport
    {
        if (QualityDecision::REJECTED === $context->earlyDecision) {
            return new QualityReport(
                documentId: $context->document->getId(),
                overallScore: null,
                categoryScores: [],
                signals: $context->signals,
                decision: QualityDecision::REJECTED,
                computedAt: new \DateTimeImmutable(),
                decisionReason: $context->earlyDecisionReason,
            );
        }

        $scoring = $this->scoringStrategy->computeScore($context->signals, $watchFile->getQualityConfig());

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
