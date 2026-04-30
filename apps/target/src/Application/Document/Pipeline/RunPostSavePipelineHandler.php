<?php

declare(strict_types=1);

namespace App\Application\Document\Pipeline;

use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Pipeline\PostSaveDocumentPipelineInterface;
use App\Domain\DocumentQuality\QualityReportBuilder;
use App\Domain\DocumentQuality\QualityReportGatewayInterface;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Asynchronous handler that runs the post-save pipeline against a freshly
 * persisted document and produces the resulting domain artefacts (currently
 * a {@see \App\Domain\DocumentQuality\QualityReport}; fuzzy-deduplication
 * outputs will land here too as Stage 1+ processors join the pipeline).
 *
 * The handler does **not** know about pipeline orchestration internals: it
 * delegates to the post-save {@see DocumentPipelineInterface}, then hands
 * the resulting context to the {@see QualityReportBuilder} which interprets
 * signals (or a halt) into a domain-level quality report.
 */
#[AsMessageHandler]
readonly class RunPostSavePipelineHandler
{
    public function __construct(
        private DocumentGatewayInterface $documentGateway,
        private WatchFileGatewayInterface $watchFileGateway,
        private PostSaveDocumentPipelineInterface $postSavePipeline,
        private QualityReportBuilder $qualityReportBuilder,
        private QualityReportGatewayInterface $qualityReportGateway,
        private EventDispatcherInterface $eventDispatcher,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(RunPostSavePipelineAction $message): void
    {
        $this->logger?->info('Running post-save pipeline', [
            'document_id' => $message->documentId,
        ]);

        $document = $this->documentGateway->get($message->documentId);

        $watchFileId = $document->getWatchFile()?->getId()
            ?? throw new \RuntimeException(\sprintf(
                'Document "%s" has no associated WatchFile.',
                $message->documentId
            ));

        $watchFile = $this->watchFileGateway->get($watchFileId);

        $context = $this->postSavePipeline->process($document, $watchFile);
        $report = $this->qualityReportBuilder->build($context, $watchFile->getQualityConfig());

        $reportId = $this->qualityReportGateway->save($report);

        $this->logger?->info('Post-save pipeline completed, quality report saved', [
            'document_id' => $message->documentId,
            'report_id' => $reportId,
            'decision' => $report->decision->value,
            'overall_score' => $report->overallScore,
            'decision_reason_fr' => $report->decisionReason?->fr,
            'decision_reason_en' => $report->decisionReason?->en,
        ]);

        $this->eventDispatcher->dispatch(new PostSavePipelineCompletedEvent(
            documentId: $message->documentId,
            reportId: $reportId,
            overallScore: $report->overallScore,
            decision: $report->decision->value,
        ));
    }
}
