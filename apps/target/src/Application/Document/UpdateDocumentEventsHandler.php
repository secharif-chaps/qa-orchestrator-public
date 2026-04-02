<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\ExtractionStatus;
use App\Domain\WatchFileEvent\WatchFileEventGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class UpdateDocumentEventsHandler
{
    use HandleTrait;

    public function __construct(
        private readonly DocumentGatewayInterface $documentGateway,
        private readonly WatchFileEventGatewayInterface $watchFileEventsGateway,
        MessageBusInterface $messageBus,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->messageBus = $messageBus;
    }

    public function __invoke(UpdateDocumentEventsAction $action): void
    {
        $this->logger?->info('UpdateDocumentEventsHandler: Processing event extraction', [
            'documentId' => $action->documentId,
            'watchFileId' => $action->watchFileId,
            'eventCount' => \count($action->events),
            'hasError' => $action->hasFailed(),
        ]);

        try {
            $this->documentGateway->get($action->documentId);
        } catch (DocumentNotFoundException $e) {
            $this->logger?->error('UpdateDocumentEventsHandler: Document not found', [
                'documentId' => $action->documentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->documentGateway->markEventsAsExtracted($action->documentId, !$action->hasFailed());

        $this->logger?->info('UpdateDocumentEventsHandler: Document flag updated', [
            'documentId' => $action->documentId,
            'eventsExtracted' => !$action->hasFailed(),
        ]);

        if ($action->hasFailed()) {
            $this->logger?->error('UpdateDocumentEventsHandler: Event extraction failed', [
                'documentId' => $action->documentId,
                'error' => $action->error,
            ]);

            $this->watchFileEventsGateway->markExtractionAsFailed(
                $action->documentId,
                $action->watchFileId,
                ExtractionStatus::FAILED,
                $action->error ?? 'Unknown error',
                new \DateTimeImmutable()
            );

            return;
        }

        $actorExtractionResult = [
            'successful' => [],
            'failed' => [],
            'total' => 0,
            'successCount' => 0,
            'failureCount' => 0,
        ];

        if ($action->hasEvents()) {
            $actorExtractionResult = $this->extractActorsFromEvents($action);
        }

        if ($action->hasEvents()) {
            $enrichedEvents = $this->enrichEventsWithActorIds(
                $action->events,
                $actorExtractionResult['successful']
            );
            $eventsWithMetadata = $this->addExtractionMetadata($enrichedEvents);

            $this->watchFileEventsGateway->saveEvents($action->documentId, $action->watchFileId, $eventsWithMetadata);

            $this->logger?->info('UpdateDocumentEventsHandler: Events saved', [
                'documentId' => $action->documentId,
                'watchFileId' => $action->watchFileId,
                'eventsStored' => \count($eventsWithMetadata),
                'actorsSuccessful' => $actorExtractionResult['successCount'],
                'actorsFailed' => $actorExtractionResult['failureCount'],
            ]);

            if ($actorExtractionResult['failureCount'] > 0) {
                $this->logger?->warning('UpdateDocumentEventsHandler: Some actors failed to extract', [
                    'documentId' => $action->documentId,
                    'failedActors' => $actorExtractionResult['failed'],
                ]);
            }
        }

        $this->logger?->info('UpdateDocumentEventsHandler: Processing completed', [
            'documentId' => $action->documentId,
            'eventsStored' => \count($action->events),
            'actorsSuccessful' => $actorExtractionResult['successCount'],
            'actorsFailed' => $actorExtractionResult['failureCount'],
        ]);
    }

    /**
     * @return array{successful: array<string, string>, failed: array<string, string>, total: int, successCount: int, failureCount: int}
     */
    private function extractActorsFromEvents(UpdateDocumentEventsAction $action): array
    {
        try {
            $extractAction = new ExtractActorsFromDocumentEventsAction(
                $action->documentId,
                $action->watchFileId,
                $action->events
            );

            /** @var array{successful: array<string, string>, failed: array<string, string>, total: int, successCount: int, failureCount: int} $result */
            $result = $this->handle($extractAction);

            return $result;
        } catch (\Exception $e) {
            $this->logger?->error('UpdateDocumentEventsHandler: Failed to extract actors', [
                'documentId' => $action->documentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'successful' => [],
                'failed' => [
                    'extraction_error' => $e->getMessage(),
                ],
                'total' => 0,
                'successCount' => 0,
                'failureCount' => 1,
            ];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @param array<string, string>            $actorIdMap
     *
     * @return array<int, array<string, mixed>>
     */
    private function enrichEventsWithActorIds(array $events, array $actorIdMap): array
    {
        $enrichedEvents = [];

        foreach ($events as $event) {
            if (!isset($event['actors']) || !\is_array($event['actors'])) {
                $enrichedEvents[] = $event;
                continue;
            }

            $enrichedActors = [];
            foreach ($event['actors'] as $actor) {
                if (!\is_array($actor)) {
                    $enrichedActors[] = $actor;
                    continue;
                }

                if (isset($actor['name']) && \is_string($actor['name'])) {
                    $actorName = trim($actor['name']);
                    $actor['actor_id'] = $actorIdMap[$actorName] ?? null;
                }

                $enrichedActors[] = $actor;
            }

            $event['actors'] = $enrichedActors;
            $enrichedEvents[] = $event;
        }

        return $enrichedEvents;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     *
     * @return array<int, array<string, mixed>>
     */
    private function addExtractionMetadata(array $events): array
    {
        $createdAt = new \DateTimeImmutable();

        return array_map(
            fn (array $event): array => array_merge($event, [
                'extraction_status' => ExtractionStatus::COMPLETED,
                'created_at' => $createdAt,
            ]),
            $events
        );
    }
}
