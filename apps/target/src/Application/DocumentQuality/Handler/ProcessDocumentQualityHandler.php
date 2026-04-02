<?php

declare(strict_types=1);

namespace App\Application\DocumentQuality\Handler;

use App\Application\DocumentQuality\Event\DocumentQualityProcessedEvent;
use App\Application\DocumentQuality\Message\ProcessDocumentQualityAction;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\DocumentQuality\DocumentProcessingPipelineInterface;
use App\Domain\DocumentQuality\QualityReportGatewayInterface;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessDocumentQualityHandler
{
    public function __construct(
        private DocumentGatewayInterface $documentGateway,
        private WatchFileGatewayInterface $watchFileGateway,
        private DocumentProcessingPipelineInterface $pipeline,
        private QualityReportGatewayInterface $qualityReportGateway,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessDocumentQualityAction $message): void
    {
        $this->logger->info('Processing document quality', [
            'document_id' => $message->documentId,
        ]);

        $document = $this->documentGateway->get($message->documentId);

        $watchFileId = $document->getWatchFile()?->getId()
            ?? throw new \RuntimeException(\sprintf(
                'Document "%s" has no associated WatchFile.',
                $message->documentId
            ));

        $watchFile = $this->watchFileGateway->get($watchFileId);

        $report = $this->pipeline->process($document, $watchFile);

        $reportId = $this->qualityReportGateway->save($report);

        $this->logger->info('Document quality report saved', [
            'document_id' => $message->documentId,
            'report_id' => $reportId,
            'decision' => $report->decision->value,
            'overall_score' => $report->overallScore,
            'decision_reason' => $report->decisionReason,
        ]);

        $this->eventDispatcher->dispatch(new DocumentQualityProcessedEvent(
            documentId: $message->documentId,
            reportId: $reportId,
            overallScore: $report->overallScore,
            decision: $report->decision->value,
        ));
    }
}
