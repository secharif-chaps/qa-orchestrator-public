<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use App\Domain\Document\ExtractionStatus;
use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Domain\WatchFileEvent\WatchFileEventGatewayInterface;

class NullWatchFileEventGateway implements WatchFileEventGatewayInterface
{
    /**
     * @var array<string, array<int, WatchFileEvent>>
     */
    private array $eventsByWatchFile = [];

    public function __construct(
        private readonly int $maxEventsLimit = 1000,
    ) {
    }

    public function getGraphData(
        string $watchFileId,
        string $interval = '1d',
        ?\DateTimeImmutable $start = null,
        ?\DateTimeImmutable $end = null,
        array $actorIds = [],
        array $eventTypes = [],
    ): array {
        // Stub implementation for testing
        return [];
    }

    public function getEventFacets(
        string $watchFileId,
        ?\DateTimeImmutable $start = null,
        ?\DateTimeImmutable $end = null,
        array $actorIds = [],
        array $eventTypes = [],
    ): array {
        // Stub implementation for testing
        return [
            'actors' => [],
            'eventTypes' => [],
            'maxStartDate' => null,
            'maxEndDate' => null,
        ];
    }

    public function getRecentEvents(string $watchFileId, int $days): array
    {
        $events = $this->eventsByWatchFile[$watchFileId] ?? [];

        return \array_slice($events, 0, $this->maxEventsLimit);
    }

    /**
     * @param array<int, WatchFileEvent> $events
     */
    public function addEventsForWatchFile(string $watchFileId, array $events): void
    {
        $this->eventsByWatchFile[$watchFileId] = $events;
    }

    public function clear(): void
    {
        $this->eventsByWatchFile = [];
    }

    public function getMaxEventsLimit(): int
    {
        return $this->maxEventsLimit;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    public function saveEvents(string $documentId, string $watchFileId, array $events): void
    {
        // Stub implementation for testing - no-op
    }

    public function markExtractionAsFailed(
        string $documentId,
        string $watchFileId,
        ExtractionStatus $status,
        string $error,
        \DateTimeImmutable $createdAt,
    ): void {
        // Stub implementation for testing - no-op
    }
}
