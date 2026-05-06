<?php

declare(strict_types=1);

namespace App\Domain\WatchFileEvent;

use App\Domain\Document\ExtractionStatus;

interface WatchFileEventGatewayInterface
{
    /**
     * Get graph data aggregated by time period.
     *
     * @param string                  $watchFileId The watchfile ID to filter events
     * @param string                  $interval    The time interval for aggregation (e.g., '1d', '1w', '1M')
     * @param \DateTimeImmutable|null $start       Start date for filtering (null = no start limit)
     * @param \DateTimeImmutable|null $end         End date for filtering (null = no end limit)
     * @param array<int, string>      $actorIds    List of actor IDs to filter by (empty = all actors)
     * @param array<int, string>      $eventTypes  List of event types to filter by (empty = all event types)
     *
     * @return array<int, array{documentsCount: int, eventsCount: int, hasEvents: bool, start: string, end: string}>
     */
    public function getGraphData(
        string $watchFileId,
        string $interval = '1d',
        ?\DateTimeImmutable $start = null,
        ?\DateTimeImmutable $end = null,
        array $actorIds = [],
        array $eventTypes = [],
    ): array;

    /**
     * Get facets for events in a watchfile.
     *
     * @param string                  $watchFileId The watchfile ID to filter events
     * @param \DateTimeImmutable|null $start       Start date for filtering (null = no start limit)
     * @param \DateTimeImmutable|null $end         End date for filtering (null = no end limit)
     * @param array<int, string>      $actorIds    List of actor IDs currently filtered (empty = no filter)
     * @param array<int, string>      $eventTypes  List of event types currently filtered (empty = no filter)
     *
     * @return array{actors: array<int, array{id: string, name: string, count: int}>, eventTypes: array<int, array{type: string, count: int}>, maxStartDate: string|null, maxEndDate: string|null}
     */
    public function getEventFacets(
        string $watchFileId,
        ?\DateTimeImmutable $start = null,
        ?\DateTimeImmutable $end = null,
        array $actorIds = [],
        array $eventTypes = [],
    ): array;

    /**
     * Get recent events for a watchfile.
     *
     * @param string $watchFileId The watchfile ID to retrieve events for
     * @param int    $days        Number of days to look back
     *
     * @return array<int, WatchFileEvent>
     */
    public function getRecentEvents(string $watchFileId, int $days): array;

    /**
     * Save events to the watchfile event store.
     *
     * @param string                           $documentId  The document ID that contains the events
     * @param string                           $watchFileId The watchfile ID these events belong to
     * @param array<int, array<string, mixed>> $events      Each event should contain 'extraction_status' (ExtractionStatus) and 'created_at' (\DateTimeImmutable)
     */
    public function saveEvents(string $documentId, string $watchFileId, array $events): void;

    /**
     * Mark an extraction as failed for a document.
     *
     * @param string             $documentId  The document ID
     * @param string             $watchFileId The watchfile ID
     * @param ExtractionStatus   $status      The extraction status (should be a failure status)
     * @param string             $error       The error message
     * @param \DateTimeImmutable $createdAt   The timestamp of the failure
     */
    public function markExtractionAsFailed(
        string $documentId,
        string $watchFileId,
        ExtractionStatus $status,
        string $error,
        \DateTimeImmutable $createdAt,
    ): void;
}
