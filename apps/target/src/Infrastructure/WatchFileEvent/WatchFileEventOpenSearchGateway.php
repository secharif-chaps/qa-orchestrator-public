<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFileEvent;

use App\Domain\Document\Document;
use App\Domain\Document\ExtractionStatus;
use App\Domain\WatchFile\Exception\WatchFileEventsRetrievalException;
use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Domain\WatchFileEvent\WatchFileEventGatewayInterface;
use App\Infrastructure\OpenSearch\Query\EventDateRangeBuilder;
use App\Infrastructure\Serializer\WatchfileEventDenormalizer;
use OpenSearch\Client;
use OpenSearch\Common\Exceptions\NoNodesAvailableException;
use OpenSearch\Common\Exceptions\OpenSearchException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Uid\Uuid;
use Webmozart\Assert\Assert;

readonly class WatchFileEventOpenSearchGateway implements WatchFileEventGatewayInterface
{
    private const int MAX_EVENTS_QUERY_SIZE = 10000;
    private const int MAX_ACTORS_AGGREGATION_SIZE = 1000;
    private const int MAX_EVENT_TYPES_AGGREGATION_SIZE = 20;
    private const int MILLISECONDS_PER_SECOND = 1000;
    private const int DEFAULT_DAYS_RANGE = 90;

    public function __construct(
        private Client $openSearch,
        private EventDateRangeBuilder $dateRangeBuilder,
        private LoggerInterface $logger,
        private int $maxEventsLimit,
        private DenormalizerInterface $denormalizer,
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
        // When filtering by actors, we count documents from event documentLinks
        // Otherwise, we query the documents index by dateCollect
        if (!empty($actorIds)) {
            // Get all events filtered by actors and count their documentLinks
            $eventsData = $this->getEventsAggregation(
                $watchFileId,
                $interval,
                $start,
                $end,
                [],
                $actorIds,
                $eventTypes
            );
            $documentsData = $this->getDocumentsFromEvents($eventsData, $interval);
        } else {
            // Query 1: Get documents aggregated by dateCollect to establish time buckets
            $documentsData = $this->getDocumentsAggregation($watchFileId, $interval, $start, $end);

            // Query 2: Get all events in the time range and process them manually
            $eventsData = $this->getEventsAggregation(
                $watchFileId,
                $interval,
                $start,
                $end,
                $documentsData,
                $actorIds,
                $eventTypes
            );
        }

        // Merge the results by time bucket
        return $this->mergeAggregations($eventsData, $documentsData, $interval);
    }

    /**
     * Get events aggregated by their duration (startDate to endDate).
     * Events are counted in all periods they overlap with.
     *
     * @param array<string, array{documentsCount: int, timestamp: int}> $documentsData Time buckets from documents
     * @param array<int, string>                                        $actorIds      List of actor IDs to filter by
     * @param array<int, string>                                        $eventTypes    List of event types to filter by
     *
     * @return array<string, array{eventsCount: int, timestamp: int, documentLinks?: array<int, string>}>
     */
    private function getEventsAggregation(
        string $watchFileId,
        string $interval,
        ?\DateTimeImmutable $start,
        ?\DateTimeImmutable $end,
        array $documentsData,
        array $actorIds = [],
        array $eventTypes = [],
    ): array {
        // Build query to get all events (not just aggregate)
        $mustClauses = [
            [
                'term' => [
                    'watchFile.id' => $watchFileId,
                ],
            ],
        ];

        // Add actor filter if provided (actors is a nested field)
        if (!empty($actorIds)) {
            $mustClauses[] = [
                'nested' => [
                    'path' => 'actors',
                    'query' => [
                        'terms' => [
                            'actors.id' => $actorIds,
                        ],
                    ],
                ],
            ];
        }

        // Add event type filter
        if (!empty($eventTypes)) {
            $eventTypeQuery = 1 === \count($eventTypes)
                ? [
                    'term' => [
                        'eventType' => reset($eventTypes),
                    ],
                ]
                : [
                    'terms' => [
                        'eventType' => $eventTypes,
                    ],
                ];
            $mustClauses[] = $eventTypeQuery;
        }

        // Add date range filter using the shared builder
        if (null !== $start || null !== $end) {
            $dateRangeClauses = $this->dateRangeBuilder->buildClauses($start, $end);
            foreach ($dateRangeClauses as $clause) {
                $mustClauses[] = $clause;
            }
        }

        $query = 1 === \count($mustClauses) ? $mustClauses[0] : [
            'bool' => [
                'must' => $mustClauses,
            ],
        ];

        // Query to get all events
        $sourceFields = ['startDate', 'endDate'];

        // When filtering by actors, we need documentLinks to count documents
        if (!empty($actorIds)) {
            $sourceFields[] = 'documentLinks';
        }

        $params = [
            'index' => WatchFileEvent::INDEX_NAME,
            'body' => [
                'size' => self::MAX_EVENTS_QUERY_SIZE,
                'query' => $query,
                '_source' => $sourceFields,
            ],
        ];

        $response = $this->openSearch->search($params);
        $responseArray = $response;
        $hits = $responseArray['hits']['hits'] ?? [];

        // When filtering by actors without explicit dates, generate buckets from events
        // Otherwise, pre-generate buckets from documents or date range
        $result = [];

        if (!empty($actorIds) && null === $start && null === $end && empty($documentsData)) {
            // Dynamic bucket generation: create buckets only for dates where events exist
            foreach ($hits as $hit) {
                $source = $hit['_source'] ?? [];
                if (!isset($source['startDate'])) {
                    continue;
                }

                $eventStart = new \DateTimeImmutable($source['startDate']);
                $eventEnd = isset($source['endDate']) && \is_string($source['endDate'])
                    ? new \DateTimeImmutable($source['endDate'])
                    : null;

                // Get documentLinks
                $documentLinks = [];
                if (isset($source['documentLinks']) && \is_array($source['documentLinks'])) {
                    foreach ($source['documentLinks'] as $link) {
                        if (\is_array($link) && isset($link['id']) && \is_string($link['id'])) {
                            $documentLinks[] = $link['id'];
                        }
                    }
                }

                // Generate buckets for this event's timespan
                $current = $this->normalizeToInterval($eventStart, $interval);
                $eventEndNormalized = $eventEnd ? $this->normalizeToInterval($eventEnd, $interval) : $current;

                // Create buckets for all periods this event spans
                do {
                    $timestamp = (int) ($current->getTimestamp() * self::MILLISECONDS_PER_SECOND);
                    $key = (string) $timestamp;

                    // Initialize bucket if it doesn't exist
                    if (!isset($result[$key])) {
                        $result[$key] = [
                            'eventsCount' => 0,
                            'timestamp' => $timestamp,
                            'documentLinks' => [],
                        ];
                    }

                    // Check if event overlaps with this bucket
                    $periodEnd = $this->calculateEndDate($current, $interval);
                    if ($this->eventOverlapsBucket($eventStart, $eventEnd, $current, $periodEnd)) {
                        ++$result[$key]['eventsCount'];
                        if (!empty($documentLinks)) {
                            /** @var array<int, string> $existingLinks */
                            $existingLinks = $result[$key]['documentLinks'];
                            $result[$key]['documentLinks'] = array_values(array_unique(array_merge(
                                $existingLinks,
                                $documentLinks
                            )));
                        }
                    }

                    $current = $this->calculateEndDate($current, $interval);
                } while ($current <= $eventEndNormalized);
            }
        } else {
            // Pre-generate buckets from documentsData or date range
            $buckets = $this->generateTimeBuckets($interval, $start, $end, $documentsData);

            // Initialize result with zero counts for all buckets
            foreach ($buckets as $bucketTimestamp) {
                $key = (string) $bucketTimestamp;
                $result[$key] = [
                    'eventsCount' => 0,
                    'timestamp' => $bucketTimestamp,
                ];

                // When filtering by actors, store document IDs per bucket
                if (!empty($actorIds)) {
                    $result[$key]['documentLinks'] = [];
                }
            }

            // Process each event and count it in all overlapping buckets
            foreach ($hits as $hit) {
                $source = $hit['_source'] ?? [];
                if (!isset($source['startDate'])) {
                    continue;
                }

                $eventStart = new \DateTimeImmutable($source['startDate']);
                $eventEnd = isset($source['endDate']) && \is_string($source['endDate'])
                    ? new \DateTimeImmutable($source['endDate'])
                    : null;

                // Get documentLinks if we're filtering by actors
                $documentLinks = [];
                if (!empty($actorIds) && isset($source['documentLinks']) && \is_array($source['documentLinks'])) {
                    foreach ($source['documentLinks'] as $link) {
                        if (\is_array($link) && isset($link['id']) && \is_string($link['id'])) {
                            $documentLinks[] = $link['id'];
                        }
                    }
                }

                // Count this event in all buckets it overlaps with
                foreach ($buckets as $bucketTimestamp) {
                    $bucketStart = new \DateTimeImmutable()
                        ->setTimestamp($bucketTimestamp / self::MILLISECONDS_PER_SECOND);
                    $bucketEnd = $this->calculateEndDate($bucketStart, $interval);

                    if ($this->eventOverlapsBucket($eventStart, $eventEnd, $bucketStart, $bucketEnd)) {
                        $key = (string) $bucketTimestamp;
                        ++$result[$key]['eventsCount'];

                        // Add documentLinks to the bucket
                        if (!empty($actorIds) && !empty($documentLinks)) {
                            /** @var array<int, string> $existingLinks */
                            $existingLinks = $result[$key]['documentLinks'] ?? [];
                            $result[$key]['documentLinks'] = array_values(array_unique(array_merge(
                                $existingLinks,
                                $documentLinks
                            )));
                        }
                    }
                }
            }
        }

        /** @var array<string, array{eventsCount: int, timestamp: int, documentLinks?: array<int, string>}> */
        return $result;
    }

    /**
     * Generate time bucket timestamps based on interval and date range.
     *
     * @param array<string, array{documentsCount: int, timestamp: int}> $documentsData
     *
     * @return array<int, int> Array of timestamps
     */
    private function generateTimeBuckets(
        string $interval,
        ?\DateTimeImmutable $start,
        ?\DateTimeImmutable $end,
        array $documentsData,
    ): array {
        // If we have documents data, use those buckets
        if (!empty($documentsData)) {
            return array_values(array_map(fn ($data) => $data['timestamp'], $documentsData));
        }

        // Generate buckets from start to end (use defaults if not provided)
        $effectiveStart = $start ?? new \DateTimeImmutable(self::DEFAULT_DAYS_RANGE . ' days ago');
        $effectiveEnd = $end ?? new \DateTimeImmutable('now');

        $buckets = [];
        $current = $effectiveStart;

        while ($current <= $effectiveEnd) {
            $buckets[] = (int) ($current->getTimestamp() * self::MILLISECONDS_PER_SECOND);
            $current = $this->calculateEndDate($current, $interval);
        }

        return $buckets;
    }

    /**
     * Check if an event overlaps with a time bucket.
     */
    private function eventOverlapsBucket(
        \DateTimeImmutable $eventStart,
        ?\DateTimeImmutable $eventEnd,
        \DateTimeImmutable $bucketStart,
        \DateTimeImmutable $bucketEnd,
    ): bool {
        // Event ends before bucket starts (if event has an end date)
        if (null !== $eventEnd && $eventEnd < $bucketStart) {
            return false;
        }

        // Event starts after bucket ends
        if ($eventStart >= $bucketEnd) {
            return false;
        }

        // Otherwise, there's an overlap
        return true;
    }

    /**
     * Extract document counts from events' documentLinks.
     * Used when filtering by actors.
     *
     * @param array<string, array{eventsCount: int, timestamp: int, documentLinks?: array<int, string>}> $eventsData
     *
     * @return array<string, array{documentsCount: int, timestamp: int}>
     */
    private function getDocumentsFromEvents(array $eventsData, string $interval): array
    {
        $result = [];

        foreach ($eventsData as $key => $data) {
            $documentLinks = $data['documentLinks'] ?? [];

            // Count unique document IDs
            $uniqueDocuments = array_unique($documentLinks);

            $result[$key] = [
                'documentsCount' => \count($uniqueDocuments),
                'timestamp' => $data['timestamp'],
            ];
        }

        return $result;
    }

    /**
     * Get documents aggregated by dateCollect.
     *
     * @return array<string, array{documentsCount: int, timestamp: int}>
     */
    private function getDocumentsAggregation(
        string $watchFileId,
        string $interval,
        ?\DateTimeImmutable $start,
        ?\DateTimeImmutable $end,
    ): array {
        $query = $this->buildQuery($watchFileId, 'dateCollect', $start, $end);
        $dateHistogram = $this->buildDateHistogram('dateCollect', $interval, $start, $end);

        $params = [
            'index' => Document::INDEX_NAME,
            'body' => [
                'size' => 0,
                'query' => $query,
                'aggs' => [
                    'documents_over_time' => [
                        'date_histogram' => $dateHistogram,
                    ],
                ],
            ],
        ];

        $response = $this->openSearch->search($params);
        $responseArray = $response;
        $buckets = $responseArray['aggregations']['documents_over_time']['buckets'] ?? [];

        $result = [];
        foreach ($buckets as $bucket) {
            if (!\is_array($bucket) || !isset($bucket['key']) || !isset($bucket['doc_count'])) {
                continue;
            }
            if (!is_numeric($bucket['key']) || !is_numeric($bucket['doc_count'])) {
                continue;
            }
            $timestamp = (int) $bucket['key'];
            $key = (string) $timestamp;
            $result[$key] = [
                'documentsCount' => (int) $bucket['doc_count'],
                'timestamp' => $timestamp,
            ];
        }

        /** @var array<string, array{documentsCount: int, timestamp: int}> */
        return $result;
    }

    /**
     * Merge events and documents aggregations by time bucket.
     *
     * @param array<string, array{eventsCount: int, timestamp: int}>    $eventsData
     * @param array<string, array{documentsCount: int, timestamp: int}> $documentsData
     *
     * @return array<int, array{documentsCount: int, eventsCount: int, hasEvents: bool, start: string, end: string}>
     */
    private function mergeAggregations(array $eventsData, array $documentsData, string $interval): array
    {
        // Get all unique timestamps from both aggregations
        $allTimestamps = array_unique(array_merge(array_keys($eventsData), array_keys($documentsData)));

        // Sort numerically (timestamps are stored as string keys)
        usort($allTimestamps, fn ($a, $b) => (int) $a <=> (int) $b);

        $graphData = [];
        foreach ($allTimestamps as $timestampKey) {
            $timestamp = (int) $timestampKey;
            $eventsCount = $eventsData[$timestampKey]['eventsCount'] ?? 0;
            $documentsCount = $documentsData[$timestampKey]['documentsCount'] ?? 0;

            // Convert timestamp to DateTimeImmutable for start
            $start = new \DateTimeImmutable()
                ->setTimestamp($timestamp / self::MILLISECONDS_PER_SECOND);

            // Calculate end based on interval
            $end = $this->calculateEndDate($start, $interval);

            $graphData[] = [
                'documentsCount' => $documentsCount,
                'eventsCount' => $eventsCount,
                'hasEvents' => $eventsCount > 0,
                'start' => $start->format('c'),
                'end' => $end->format('c'),
            ];
        }

        return $graphData;
    }

    /**
     * Build OpenSearch query with date range filtering.
     *
     * @return array<string, mixed>
     */
    private function buildQuery(
        string $watchFileId,
        string $dateField,
        ?\DateTimeImmutable $start,
        ?\DateTimeImmutable $end,
    ): array {
        $mustClauses = [
            [
                'term' => [
                    'watchFile.id' => $watchFileId,
                ],
            ],
        ];

        // Add date range filter if start or end is provided
        if (null !== $start || null !== $end) {
            $rangeFilter = [];
            if (null !== $start) {
                $rangeFilter['gte'] = $start->format('Y-m-d\TH:i:s\Z');
            }
            if (null !== $end) {
                $rangeFilter['lte'] = $end->format('Y-m-d\TH:i:s\Z');
            }

            $mustClauses[] = [
                'range' => [
                    $dateField => $rangeFilter,
                ],
            ];
        }

        // Return simple term query if only watchFileId filter
        if (1 === \count($mustClauses)) {
            return $mustClauses[0];
        }

        // Return bool query with multiple must clauses
        return [
            'bool' => [
                'must' => $mustClauses,
            ],
        ];
    }

    /**
     * Build date histogram configuration.
     *
     * @return array<string, mixed>
     */
    private function buildDateHistogram(
        string $field,
        string $interval,
        ?\DateTimeImmutable $start,
        ?\DateTimeImmutable $end,
    ): array {
        $histogram = [
            'field' => $field,
            'calendar_interval' => $interval,
            'min_doc_count' => 0,
        ];

        // Add extended_bounds based on provided dates or default range
        $bounds = [];
        if (null !== $start) {
            $bounds['min'] = $start->format('Y-m-d\TH:i:s\Z');
        }
        if (null !== $end) {
            $bounds['max'] = $end->format('Y-m-d\TH:i:s\Z');
        }
        if (isset($bounds['min']) || isset($bounds['max'])) {
            $histogram['extended_bounds'] = $bounds;
        }
        // If no dates provided, don't add extended_bounds to get all data

        return $histogram;
    }

    private function calculateEndDate(\DateTimeImmutable $start, string $interval): \DateTimeImmutable
    {
        return match ($interval) {
            '1h' => $start->modify('+1 hour'),
            '1d' => $start->modify('+1 day'),
            '1w' => $start->modify('+1 week'),
            '1M' => $start->modify('+1 month'),
            '1y' => $start->modify('+1 year'),
            default => $start->modify('+1 day'),
        };
    }

    /**
     * Normalize a date to the start of the interval period.
     */
    private function normalizeToInterval(\DateTimeImmutable $date, string $interval): \DateTimeImmutable
    {
        return match ($interval) {
            '1h' => $date->setTime((int) $date->format('H'), 0, 0),
            '1d' => $date->setTime(0, 0, 0),
            '1w' => $date->modify('monday this week')
->setTime(0, 0, 0),
            '1M' => $date->modify('first day of this month')
->setTime(0, 0, 0),
            '1y' => $date->setDate((int) $date->format('Y'), 1, 1)
->setTime(0, 0, 0),
            default => $date->setTime(0, 0, 0),
        };
    }

    public function getEventFacets(
        string $watchFileId,
        ?\DateTimeImmutable $start = null,
        ?\DateTimeImmutable $end = null,
        array $actorIds = [],
        array $eventTypes = [],
    ): array {
        // Build query to filter events by watchFile and optional date range
        $mustClauses = [
            [
                'term' => [
                    'watchFile.id' => $watchFileId,
                ],
            ],
        ];

        // Add actor filter if provided (actors is a nested field)
        if (!empty($actorIds)) {
            $mustClauses[] = [
                'nested' => [
                    'path' => 'actors',
                    'query' => [
                        'terms' => [
                            'actors.id' => $actorIds,
                        ],
                    ],
                ],
            ];
        }

        // Add event type filter
        if (!empty($eventTypes)) {
            $eventTypeQuery = 1 === \count($eventTypes)
                ? [
                    'term' => [
                        'eventType' => reset($eventTypes),
                    ],
                ]
                : [
                    'terms' => [
                        'eventType' => $eventTypes,
                    ],
                ];
            $mustClauses[] = $eventTypeQuery;
        }

        // Add date range filters using the shared builder
        if (null !== $start || null !== $end) {
            $dateRangeClauses = $this->dateRangeBuilder->buildClauses($start, $end);
            foreach ($dateRangeClauses as $clause) {
                $mustClauses[] = $clause;
            }
        }

        $query = 1 === \count($mustClauses) ? $mustClauses[0] : [
            'bool' => [
                'must' => $mustClauses,
            ],
        ];

        // Build aggregations
        $aggregations = [
            'max_start_date' => [
                'max' => [
                    'field' => 'startDate',
                ],
            ],
            'max_end_date' => [
                'max' => [
                    'field' => 'endDate',
                ],
            ],
        ];

        // Always add actors aggregation to show available actors in facets
        // When actor filter is applied, the aggregation will show only matching actors
        $aggregations['actors'] = [
            'nested' => [
                'path' => 'actors',
            ],
            'aggs' => [
                'actor_ids' => [
                    'terms' => [
                        'field' => 'actors.id',
                        'size' => self::MAX_ACTORS_AGGREGATION_SIZE,
                    ],
                    'aggs' => [
                        'actor_name' => [
                            'terms' => [
                                'field' => 'actors.name',
                                'size' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Add event types aggregation
        $eventTypesTermsConfig = [
            'field' => 'eventType',
            'size' => self::MAX_EVENT_TYPES_AGGREGATION_SIZE,
        ];
        if (!empty($eventTypes)) {
            $eventTypesTermsConfig['include'] = $eventTypes;
            $eventTypesTermsConfig['min_doc_count'] = 0;
        }
        $aggregations['eventTypes'] = [
            'terms' => $eventTypesTermsConfig,
        ];

        // Query with aggregations to get facets
        $params = [
            'index' => WatchFileEvent::INDEX_NAME,
            'body' => [
                'size' => 0,
                'query' => $query,
                'aggs' => $aggregations,
            ],
        ];

        $response = $this->openSearch->search($params);
        $responseArray = $response;

        // Extract max dates
        $maxStartDate = $responseArray['aggregations']['max_start_date']['value'] ?? null;
        $maxEndDate = $responseArray['aggregations']['max_end_date']['value'] ?? null;

        // Convert timestamps to ISO 8601 strings
        $maxStartDateString = null;
        if (null !== $maxStartDate) {
            $maxStartDateString = new \DateTimeImmutable()
                ->setTimestamp((int) ($maxStartDate / self::MILLISECONDS_PER_SECOND))
                ->format('c');
        }

        $maxEndDateString = null;
        if (null !== $maxEndDate) {
            $maxEndDateString = new \DateTimeImmutable()
                ->setTimestamp((int) ($maxEndDate / self::MILLISECONDS_PER_SECOND))
                ->format('c');
        }

        // Extract actor facets (always extract, even when actor filter is applied)
        $actorFacets = [];
        if (isset($responseArray['aggregations']['actors']['actor_ids']['buckets'])) {
            $buckets = $responseArray['aggregations']['actors']['actor_ids']['buckets'];
            Assert::isArray($buckets);

            foreach ($buckets as $bucket) {
                $actorId = $bucket['key'];
                $count = $bucket['doc_count'];

                // Extract actor name from the nested terms aggregation
                $actorName = $actorId; // Default to ID
                if (!empty($bucket['actor_name']['buckets'])) {
                    $nameBucket = $bucket['actor_name']['buckets'][0];
                    Assert::isArray($nameBucket);
                    if (isset($nameBucket['key']) && \is_string($nameBucket['key'])) {
                        $actorName = $nameBucket['key'];
                    }
                }

                $actorFacets[] = [
                    'id' => $actorId,
                    'name' => $actorName,
                    'count' => $count,
                ];
            }
        }

        // Extract event types facets
        $eventTypeFacets = [];
        if (isset($responseArray['aggregations']['eventTypes']) && \is_array(
            $responseArray['aggregations']['eventTypes']
        ) && isset($responseArray['aggregations']['eventTypes']['buckets'])) {
            $buckets = $responseArray['aggregations']['eventTypes']['buckets'];
            Assert::isArray($buckets);

            foreach ($buckets as $bucket) {
                $eventTypeFacets[] = [
                    'type' => $bucket['key'],
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        $result = [
            'actors' => $actorFacets,
            'eventTypes' => $eventTypeFacets,
            'maxStartDate' => $maxStartDateString,
            'maxEndDate' => $maxEndDateString,
        ];

        return $result;
    }

    public function getRecentEvents(string $watchFileId, int $days): array
    {
        try {
            $response = $this->openSearch->search([
                'index' => WatchFileEvent::INDEX_NAME,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'range' => [
                                        'startDate' => [
                                            'gte' => \sprintf('now-%dd/d', $days),
                                            'lte' => 'now/d',
                                        ],
                                    ],
                                ],
                            ],
                            'filter' => [
                                [
                                    'term' => [
                                        'watchFile.id' => $watchFileId,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'sort' => [
                        [
                            'startDate' => [
                                'order' => 'desc',
                            ],
                        ],
                    ],
                    'size' => $this->maxEventsLimit,
                ],
            ]);

            $hits = $response['hits']['hits'] ?? [];
            $totalHits = $response['hits']['total']['value'] ?? 0;
            $events = [];

            foreach ($hits as $hit) {
                $source = $hit['_source'] ?? [];
                $events[] = $this->buildWatchFileEvent($source);
            }

            if (\count($events) >= $this->maxEventsLimit && $totalHits > $this->maxEventsLimit) {
                $this->logger->warning('WatchFile events limit reached, some events may be missing', [
                    'watch_file_id' => $watchFileId,
                    'days' => $days,
                    'retrieved_count' => \count($events),
                    'total_available' => $totalHits,
                    'limit' => $this->maxEventsLimit,
                ]);
            }

            $this->logger->info('Retrieved recent watchfile events from OpenSearch', [
                'watch_file_id' => $watchFileId,
                'days' => $days,
                'count' => \count($events),
                'total_available' => $totalHits,
            ]);

            return $events;
        } catch (OpenSearchException|NoNodesAvailableException $e) {
            $this->logger->error('Failed to retrieve watchfile events from OpenSearch', [
                'watch_file_id' => $watchFileId,
                'days' => $days,
                'error' => $e->getMessage(),
                'exception_type' => $e::class,
            ]);

            throw WatchFileEventsRetrievalException::fromOpenSearchError($watchFileId, $e);
        }
    }

    /**
     * @param array<string, mixed> $source
     */
    private function buildWatchFileEvent(array $source): WatchFileEvent
    {
        return $this->denormalizer->denormalize($source, WatchFileEvent::class, null, [
            'groups' => [WatchfileEventDenormalizer::SERIALIZATION_GROUP],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    public function saveEvents(string $documentId, string $watchFileId, array $events): void
    {
        if (empty($events)) {
            return;
        }

        $bulkBody = [];
        $skippedCount = 0;

        foreach ($events as $event) {
            $eventId = Uuid::v4()->toString();

            if (!isset($event['title']) || !\is_array($event['title'])) {
                $this->logger->error('WatchFileEventOpenSearchGateway: Event missing required title field', [
                    'eventId' => $eventId,
                    'documentId' => $documentId,
                ]);
                ++$skippedCount;
                continue;
            }

            $title = $event['title'];

            if (!isset($title['fr']) || !isset($title['en'])) {
                $this->logger->error('WatchFileEventOpenSearchGateway: Title missing required fr or en keys', [
                    'eventId' => $eventId,
                    'documentId' => $documentId,
                    'title' => $title,
                ]);
                ++$skippedCount;
                continue;
            }

            try {
                Assert::keyExists($event, 'start_date', 'Event missing required start_date field');
                Assert::keyExists($event, 'description', 'Event missing required description field');
                Assert::keyExists($event, 'event_type', 'Event missing required event_type field');
            } catch (\InvalidArgumentException $e) {
                $this->logger->error('WatchFileEventOpenSearchGateway: ' . $e->getMessage(), [
                    'eventId' => $eventId,
                    'documentId' => $documentId,
                ]);
                ++$skippedCount;
                continue;
            }

            $actors = $event['actors'] ?? [];
            /** @var array<int, array<string, mixed>> $validActors */
            $validActors = \is_array($actors) ? array_filter($actors, fn ($actor): bool => \is_array($actor)) : [];
            $enrichedActors = $this->enrichActors($validActors, $watchFileId);

            $this->logger->debug('WatchFileEventOpenSearchGateway: Processing event', [
                'eventId' => $eventId,
                'actorsCount' => \is_array($actors) ? \count($actors) : 0,
                'validActorsCount' => \count($validActors),
                'enrichedActorsCount' => \count($enrichedActors),
                'enrichedActors' => $enrichedActors,
            ]);

            if (isset($event['created_at']) && $event['created_at'] instanceof \DateTimeImmutable) {
                $createdAt = $event['created_at'];
            } else {
                $createdAt = new \DateTimeImmutable();
            }

            $extractionStatusValue = $event['extraction_status'] ?? ExtractionStatus::PENDING;
            if ($extractionStatusValue instanceof ExtractionStatus) {
                $extractionStatus = $extractionStatusValue->value;
            } elseif (\is_string($extractionStatusValue)) {
                $extractionStatus = $extractionStatusValue;
            } else {
                $extractionStatus = ExtractionStatus::PENDING->value;
            }

            $startDate = $event['start_date'];
            $endDate = $event['end_date'] ?? $startDate;

            $bulkBody[] = [
                'index' => [
                    '_index' => WatchFileEvent::INDEX_NAME,
                    '_id' => $eventId,
                ],
            ];

            $bulkBody[] = [
                'id' => $eventId,
                'title' => $title,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'description' => $event['description'],
                'eventType' => $event['event_type'],
                'actors' => $enrichedActors,
                'documentLinks' => [
                    [
                        'id' => $documentId,
                        'text_extract' => $event['text_extract'] ?? '',
                    ],
                ],
                'extractionStatus' => $extractionStatus,
                'createdAt' => $createdAt->format('c'),
                'watchFile' => [
                    'id' => $watchFileId,
                ],
            ];
        }

        if ([] === $bulkBody) {
            $this->logger->warning('WatchFileEventOpenSearchGateway: No valid events to index after validation', [
                'documentId' => $documentId,
                'watchFileId' => $watchFileId,
                'originalEventCount' => \count($events),
                'skippedCount' => $skippedCount,
            ]);

            return;
        }

        $response = $this->openSearch->bulk([
            'body' => $bulkBody,
        ]);

        $indexedCount = (int) (\count($bulkBody) / 2);

        if ($response['errors'] ?? false) {
            $failedItems = [];
            foreach ($response['items'] ?? [] as $item) {
                $indexResult = $item['index'] ?? [];
                if (isset($indexResult['error'])) {
                    $failedItems[] = [
                        'id' => $indexResult['_id'] ?? 'unknown',
                        'error' => $indexResult['error']['reason'] ?? 'Unknown error',
                        'type' => $indexResult['error']['type'] ?? 'unknown',
                    ];
                }
            }

            $this->logger->error('WatchFileEventOpenSearchGateway: Some events failed to index', [
                'documentId' => $documentId,
                'watchFileId' => $watchFileId,
                'totalEvents' => $indexedCount,
                'failedCount' => \count($failedItems),
                'failedItems' => $failedItems,
            ]);
        }

        $this->logger->info('WatchFileEventOpenSearchGateway: Events saved', [
            'documentId' => $documentId,
            'watchFileId' => $watchFileId,
            'eventCount' => $indexedCount,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $actors
     *
     * @return array<int, array<string, mixed>>
     */
    private function enrichActors(array $actors, string $watchFileId): array
    {
        return array_filter(
            array_map(
                function (array $actor) use ($watchFileId): ?array {
                    if (!isset($actor['actor_id']) || !\is_string($actor['actor_id'])) {
                        $this->logger->error('WatchFileEventOpenSearchGateway: Actor missing actor_id', [
                            'actor' => $actor,
                            'watchFileId' => $watchFileId,
                        ]);

                        return null;
                    }

                    return [
                        'id' => $actor['actor_id'],
                        'name' => $actor['name'] ?? '',
                        'role' => $actor['role'] ?? '',
                    ];
                },
                $actors
            ),
            fn (?array $actor): bool => null !== $actor
        );
    }

    public function markExtractionAsFailed(
        string $documentId,
        string $watchFileId,
        ExtractionStatus $status,
        string $error,
        \DateTimeImmutable $createdAt,
    ): void {
        $eventId = Uuid::v4()->toString();

        $this->openSearch->index([
            'index' => WatchFileEvent::INDEX_NAME,
            'id' => $eventId,
            'body' => [
                'id' => $eventId,
                'document_links' => [
                    [
                        'id' => $documentId,
                    ],
                ],
                'extraction_status' => $status->value,
                'extraction_error' => $error,
                'created_at' => $createdAt->format('c'),
                'watchfile_id' => $watchFileId,
            ],
        ]);

        $this->logger->warning('WatchFileEventOpenSearchGateway: Extraction marked as failed', [
            'documentId' => $documentId,
            'watchFileId' => $watchFileId,
            'status' => $status->value,
            'error' => $error,
        ]);
    }
}
