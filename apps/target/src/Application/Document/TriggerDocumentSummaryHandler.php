<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Application\Agent\DocumentSummaryTriggerAgent;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\SummaryStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class TriggerDocumentSummaryHandler
{
    public function __construct(
        private readonly DocumentGatewayInterface $documentGateway,
        private readonly MessageBusInterface $messageBus,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(TriggerDocumentSummaryAction $action): void
    {
        $document = $this->documentGateway->get($action->documentId);

        // Check if summary has already been generated
        if (SummaryStatus::COMPLETED === $document->getSummaryStatus()) {
            $this->logger?->info('Skipping summary generation - document summary already completed', [
                'document_id' => $document->getId(),
                'summary_generated_at' => $document->getSummaryGeneratedAt()?->format(\DateTimeInterface::ATOM),
            ]);

            return;
        }

        // Check if summary generation is already pending
        if (SummaryStatus::PENDING === $document->getSummaryStatus()) {
            $this->logger?->info('Skipping summary generation - document summary generation already pending', [
                'document_id' => $document->getId(),
            ]);

            return;
        }

        $summaryData = [
            'id' => $document->getId(),
            'content' => $document->getContent(),
        ];

        $this->logger?->info('Triggering document summary generation workflow', [
            'document_id' => $document->getId(),
            'document_content_length' => \strlen($document->getContent()),
        ]);

        try {
            // Mark summary as pending before triggering the agent
            $document->setSummaryStatus(SummaryStatus::PENDING);
            $this->documentGateway->save($document);

            $this->messageBus->dispatch(
                new DocumentSummaryTriggerAgent(data: $summaryData, triggeredAt: new \DateTime())
            );

            $this->logger?->info('Document summary generation workflow triggered successfully', [
                'document_id' => $document->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Failed to trigger document summary generation workflow', [
                'document_id' => $document->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
