<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Application\Agent\EventExtractionTriggerAgent;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Exception\DocumentWithoutWatchFileException;
use App\Domain\WatchFileEvent\WatchFileEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class TriggerEventExtractionHandler
{
    use HandleTrait;

    public function __construct(
        private readonly DocumentGatewayInterface $documentGateway,
        private MessageBusInterface $messageBus,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(TriggerEventExtractionAction $action): void
    {
        $document = $this->documentGateway->get($action->documentId);

        $watchFile = $document->getWatchFile();
        if (null === $watchFile) {
            $this->logger?->error('Cannot trigger event extraction - document has no watch file', [
                'document_id' => $document->getId(),
            ]);

            throw DocumentWithoutWatchFileException::forDocumentId($document->getId());
        }

        if ($document->isEventsExtracted() || null !== $document->getEventsExtractedStartedAt()) {
            $this->logger?->info('Skipping event extraction - document has already been processed or is in progress', [
                'document_id' => $document->getId(),
                'events_extracted_at' => $document->getEventsExtractedAt()?->format(\DateTimeInterface::ATOM),
                'events_extracted_started_at' => $document->getEventsExtractedStartedAt()?->format(
                    \DateTimeInterface::ATOM
                ),
            ]);

            return;
        }

        $watchFileId = $watchFile->getId();
        $referenceSubject = $watchFile->getReferenceSubject();
        $recentEventsContext = $this->getRecentEventsContext($watchFileId);

        $extractionData = [
            'documentId' => $document->getId(),
            'watchFileId' => $watchFileId,
            'documentContent' => $document->getContent(),
            'referenceSubject' => $referenceSubject,
            'recentEvents' => $recentEventsContext,
        ];

        $this->logger?->info('Triggering event extraction workflow', [
            'document_id' => $document->getId(),
            'watch_file_id' => $watchFileId,
            'recent_events_count' => \count($recentEventsContext),
            'document_content_length' => \strlen($document->getContent()),
        ]);

        try {
            $document->startEventsExtraction();
            $this->documentGateway->save($document);

            $this->messageBus->dispatch(
                new EventExtractionTriggerAgent(data: $extractionData, triggeredAt: new \DateTime())
            );

            $this->logger?->info('Event extraction workflow triggered successfully', [
                'document_id' => $document->getId(),
                'watch_file_id' => $watchFileId,
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Failed to trigger event extraction workflow', [
                'document_id' => $document->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array<int, WatchFileEvent>
     */
    private function getRecentEventsContext(string $watchFileId): array
    {
        $action = new GetRecentEventsContextAction($watchFileId, daysBack: 15);

        /** @var array<int, WatchFileEvent> $result */
        $result = $this->handle($action);

        return $result;
    }
}
