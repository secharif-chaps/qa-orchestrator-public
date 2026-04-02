<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFileEvent;

use App\Domain\Document\Document;
use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Infrastructure\OpenSearch\Query\EventDateRangeBuilder;
use App\Infrastructure\WatchFileEvent\WatchFileEventOpenSearchGateway;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(WatchFileEventOpenSearchGateway::class)]
final class WatchFileEventOpenSearchGatewayTest extends TestCase
{
    private Client&MockObject $openSearch;
    private EventDateRangeBuilder $dateRangeBuilder;
    private LoggerInterface&Stub $logger;
    private DenormalizerInterface&Stub $denormalizer;
    private WatchFileEventOpenSearchGateway $gateway;
    private int $maxEventsLimit = 1000;

    protected function setUp(): void
    {
        /** @var Client&MockObject $openSearch */
        $openSearch = $this->createMock(Client::class);
        $this->openSearch = $openSearch;
        $this->dateRangeBuilder = new EventDateRangeBuilder();
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->denormalizer = $this->createStub(DenormalizerInterface::class);

        $this->gateway = new WatchFileEventOpenSearchGateway(
            $this->openSearch,
            $this->dateRangeBuilder,
            $this->logger,
            $this->maxEventsLimit,
            $this->denormalizer,
        );
    }

    #[Test]
    public function itBuildsDocumentsQueryWithWatchFileIdOnly(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';

        $this->openSearch
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (array $params) use ($watchFileId) {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    // First call: documents aggregation
                    self::assertSame(Document::INDEX_NAME, $params['index']);
                    self::assertSame(0, $params['body']['size']);

                    $query = $params['body']['query'];
                    self::assertArrayHasKey('term', $query);
                    self::assertSame($watchFileId, $query['term']['watchFile.id']);

                    // Check date histogram aggregation
                    self::assertArrayHasKey('aggs', $params['body']);
                    self::assertArrayHasKey('documents_over_time', $params['body']['aggs']);
                    $histogram = $params['body']['aggs']['documents_over_time']['date_histogram'];
                    self::assertSame('dateCollect', $histogram['field']);
                    self::assertSame('1d', $histogram['calendar_interval']);
                    self::assertSame(0, $histogram['min_doc_count']);
                } else {
                    // Second call: events query
                    self::assertSame(WatchFileEvent::INDEX_NAME, $params['index']);
                    self::assertSame(10000, $params['body']['size']);

                    $query = $params['body']['query'];
                    self::assertArrayHasKey('term', $query);
                    self::assertSame($watchFileId, $query['term']['watchFile.id']);
                }

                return $this->createDefaultResponse();
            });

        $this->gateway->getGraphData($watchFileId);
    }

    #[Test]
    public function itBuildsDocumentsQueryWithCustomInterval(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $interval = '1w';

        $this->openSearch
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (array $params) use ($interval) {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    // First call: documents aggregation
                    $histogram = $params['body']['aggs']['documents_over_time']['date_histogram'];
                    self::assertSame($interval, $histogram['calendar_interval']);
                }

                return $this->createDefaultResponse();
            });

        $this->gateway->getGraphData($watchFileId, $interval);
    }

    #[Test]
    public function itBuildsDocumentsQueryWithDateRange(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $start = new \DateTimeImmutable('2025-10-01T00:00:00+00:00');
        $end = new \DateTimeImmutable('2025-10-31T23:59:59+00:00');

        $this->openSearch
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (array $params) use ($watchFileId) {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    // First call: documents aggregation
                    $query = $params['body']['query'];
                    self::assertArrayHasKey('bool', $query);
                    self::assertArrayHasKey('must', $query['bool']);
                    self::assertCount(2, $query['bool']['must']);

                    // Check watchFile.id term
                    self::assertArrayHasKey('term', $query['bool']['must'][0]);
                    self::assertSame($watchFileId, $query['bool']['must'][0]['term']['watchFile.id']);

                    // Check date range
                    self::assertArrayHasKey('range', $query['bool']['must'][1]);
                    self::assertArrayHasKey('dateCollect', $query['bool']['must'][1]['range']);
                    self::assertSame('2025-10-01T00:00:00Z', $query['bool']['must'][1]['range']['dateCollect']['gte']);
                    self::assertSame('2025-10-31T23:59:59Z', $query['bool']['must'][1]['range']['dateCollect']['lte']);

                    // Check extended_bounds in histogram
                    $histogram = $params['body']['aggs']['documents_over_time']['date_histogram'];
                    self::assertArrayHasKey('extended_bounds', $histogram);
                    self::assertSame('2025-10-01T00:00:00Z', $histogram['extended_bounds']['min']);
                    self::assertSame('2025-10-31T23:59:59Z', $histogram['extended_bounds']['max']);
                }

                return $this->createDefaultResponse();
            });

        $this->gateway->getGraphData($watchFileId, '1d', $start, $end);
    }

    #[Test]
    public function itBuildsEventsQueryWithActorFilter(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $actorIds = ['actor-1', 'actor-2'];

        $this->openSearch
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (array $params) use ($watchFileId, $actorIds) {
                // Only events query when filtering by actors (no documents query)
                self::assertSame(WatchFileEvent::INDEX_NAME, $params['index']);
                self::assertSame(10000, $params['body']['size']);

                $query = $params['body']['query'];
                self::assertArrayHasKey('bool', $query);
                self::assertArrayHasKey('must', $query['bool']);
                self::assertCount(2, $query['bool']['must']);

                // Check watchFile.id term
                self::assertArrayHasKey('term', $query['bool']['must'][0]);
                self::assertSame($watchFileId, $query['bool']['must'][0]['term']['watchFile.id']);

                // Check actors nested filter
                self::assertArrayHasKey('nested', $query['bool']['must'][1]);
                self::assertSame('actors', $query['bool']['must'][1]['nested']['path']);
                self::assertArrayHasKey('query', $query['bool']['must'][1]['nested']);
                self::assertArrayHasKey('terms', $query['bool']['must'][1]['nested']['query']);
                self::assertSame($actorIds, $query['bool']['must'][1]['nested']['query']['terms']['actors.id']);

                // Check that documentLinks is requested in _source
                self::assertContains('documentLinks', $params['body']['_source']);

                return $this->createDefaultResponse();
            });

        $this->gateway->getGraphData($watchFileId, '1d', null, null, $actorIds);
    }

    #[Test]
    public function itBuildsEventsQueryWithActorFilterAndDateRange(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $actorIds = ['actor-1'];
        $start = new \DateTimeImmutable('2025-10-01T00:00:00+00:00');
        $end = new \DateTimeImmutable('2025-10-31T23:59:59+00:00');

        $this->openSearch
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (array $params) use ($watchFileId, $actorIds) {
                $query = $params['body']['query'];
                self::assertArrayHasKey('bool', $query);
                self::assertArrayHasKey('must', $query['bool']);

                // Should have: watchFile.id term, actors nested, and date range clauses
                self::assertGreaterThanOrEqual(3, \count($query['bool']['must']));

                // Verify watchFile.id
                self::assertSame($watchFileId, $query['bool']['must'][0]['term']['watchFile.id']);

                // Verify actors nested filter
                self::assertArrayHasKey('nested', $query['bool']['must'][1]);
                self::assertSame($actorIds, $query['bool']['must'][1]['nested']['query']['terms']['actors.id']);

                // Verify date range clauses exist (EventDateRangeBuilder creates 2 clauses)
                // 1. range on startDate (lte endDate)
                // 2. bool should on endDate (gte startDate OR doesn't exist)
                $hasStartDateRangeClause = false;
                $hasEndDateBoolClause = false;
                foreach ($query['bool']['must'] as $clause) {
                    if (isset($clause['range']['startDate']['lte'])) {
                        $hasStartDateRangeClause = true;
                    }
                    if (isset($clause['bool']['should'])) {
                        // Check if it's the endDate bool clause
                        foreach ($clause['bool']['should'] as $shouldClause) {
                            if (isset($shouldClause['range']['endDate']['gte'])) {
                                $hasEndDateBoolClause = true;
                                break;
                            }
                        }
                    }
                }
                self::assertTrue($hasStartDateRangeClause, 'Should have startDate range clause (lte)');
                self::assertTrue($hasEndDateBoolClause, 'Should have endDate bool clause (gte or doesn\'t exist)');

                return $this->createDefaultResponse();
            });

        $this->gateway->getGraphData($watchFileId, '1d', $start, $end, $actorIds);
    }

    #[Test]
    public function itBuildsEventFacetsQueryWithoutFilters(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';

        $this->openSearch
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (array $params) use ($watchFileId) {
                self::assertSame(WatchFileEvent::INDEX_NAME, $params['index']);
                self::assertSame(0, $params['body']['size']);

                // Check query
                $query = $params['body']['query'];
                self::assertArrayHasKey('term', $query);
                self::assertSame($watchFileId, $query['term']['watchFile.id']);

                // Check aggregations
                $aggs = $params['body']['aggs'];
                self::assertArrayHasKey('max_start_date', $aggs);
                self::assertArrayHasKey('max_end_date', $aggs);
                self::assertArrayHasKey('actors', $aggs);

                // Verify max_start_date aggregation
                self::assertSame('startDate', $aggs['max_start_date']['max']['field']);

                // Verify max_end_date aggregation
                self::assertSame('endDate', $aggs['max_end_date']['max']['field']);

                // Verify actors aggregation structure
                self::assertArrayHasKey('nested', $aggs['actors']);
                self::assertSame('actors', $aggs['actors']['nested']['path']);
                self::assertArrayHasKey('aggs', $aggs['actors']);
                self::assertArrayHasKey('actor_ids', $aggs['actors']['aggs']);
                self::assertSame('actors.id', $aggs['actors']['aggs']['actor_ids']['terms']['field']);
                self::assertSame(1000, $aggs['actors']['aggs']['actor_ids']['terms']['size']);

                // Verify eventTypes aggregation structure
                self::assertArrayHasKey('eventTypes', $aggs);
                self::assertSame('eventType', $aggs['eventTypes']['terms']['field']);
                self::assertSame(20, $aggs['eventTypes']['terms']['size']);

                return [
                    'hits' => [
                        'hits' => [],
                        'total' => [
                            'value' => 0,
                        ],
                    ],
                    'aggregations' => [
                        'max_start_date' => [
                            'value' => null,
                        ],
                        'max_end_date' => [
                            'value' => null,
                        ],
                        'actors' => [
                            'actor_ids' => [
                                'buckets' => [],
                            ],
                        ],
                        'eventTypes' => [
                            'buckets' => [],
                        ],
                    ],
                ];
            });

        $this->gateway->getEventFacets($watchFileId);
    }

    #[Test]
    public function itBuildsEventFacetsQueryWithActorFilter(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $actorIds = ['actor-1', 'actor-2'];

        $this->openSearch
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (array $params) use ($actorIds) {
                $query = $params['body']['query'];
                self::assertArrayHasKey('bool', $query);
                self::assertArrayHasKey('must', $query['bool']);
                self::assertCount(2, $query['bool']['must']);

                // Check actors nested filter
                self::assertArrayHasKey('nested', $query['bool']['must'][1]);
                self::assertSame($actorIds, $query['bool']['must'][1]['nested']['query']['terms']['actors.id']);

                // Actors aggregation should always be present, even when filtering by actors
                $aggs = $params['body']['aggs'];
                self::assertArrayHasKey('actors', $aggs, 'Actors aggregation should always be present');
                self::assertArrayHasKey('eventTypes', $aggs, 'EventTypes aggregation should always be present');
                self::assertArrayHasKey('max_start_date', $aggs);
                self::assertArrayHasKey('max_end_date', $aggs);

                return [
                    'hits' => [
                        'hits' => [],
                        'total' => [
                            'value' => 0,
                        ],
                    ],
                    'aggregations' => [
                        'max_start_date' => [
                            'value' => null,
                        ],
                        'max_end_date' => [
                            'value' => null,
                        ],
                        'actors' => [
                            'actor_ids' => [
                                'buckets' => [
                                    [
                                        'key' => 'actor-1',
                                        'doc_count' => 10,
                                        'actor_name' => [
                                            'buckets' => [
                                                [
                                                    'key' => 'Actor 1',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'eventTypes' => [
                            'buckets' => [
                                [
                                    'key' => 'commercial_business',
                                    'doc_count' => 5,
                                ],
                            ],
                        ],
                    ],
                ];
            });

        $result = $this->gateway->getEventFacets($watchFileId, null, null, $actorIds);

        // Verify that actors facets are returned even when filtering by actors
        self::assertArrayHasKey('actors', $result);
        self::assertNotEmpty($result['actors']);
        self::assertCount(1, $result['actors']);
        self::assertSame('actor-1', $result['actors'][0]['id']);
        self::assertSame('Actor 1', $result['actors'][0]['name']);
        self::assertSame(10, $result['actors'][0]['count']);

        // Verify eventTypes is present
        self::assertArrayHasKey('eventTypes', $result);
        self::assertNotEmpty($result['eventTypes']);
        self::assertCount(1, $result['eventTypes']);
        self::assertSame('commercial_business', $result['eventTypes'][0]['type']);
        self::assertSame(5, $result['eventTypes'][0]['count']);
    }

    #[Test]
    public function itBuildsEventFacetsQueryWithDateRange(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $start = new \DateTimeImmutable('2025-10-01T00:00:00+00:00');
        $end = new \DateTimeImmutable('2025-10-31T23:59:59+00:00');

        $this->openSearch
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (array $params) {
                $query = $params['body']['query'];
                self::assertArrayHasKey('bool', $query);
                self::assertArrayHasKey('must', $query['bool']);

                // Should have watchFile.id term and date range clauses
                self::assertGreaterThanOrEqual(3, \count($query['bool']['must']));

                // Verify date range clauses exist (EventDateRangeBuilder format)
                $hasStartDateRangeClause = false;
                $hasEndDateBoolClause = false;
                foreach ($query['bool']['must'] as $clause) {
                    if (isset($clause['range']['startDate']['lte'])) {
                        $hasStartDateRangeClause = true;
                        self::assertSame('2025-10-31T23:59:59Z', $clause['range']['startDate']['lte']);
                    }
                    if (isset($clause['bool']['should'])) {
                        foreach ($clause['bool']['should'] as $shouldClause) {
                            if (isset($shouldClause['range']['endDate']['gte'])) {
                                $hasEndDateBoolClause = true;
                                self::assertSame('2025-10-01T00:00:00Z', $shouldClause['range']['endDate']['gte']);
                                break;
                            }
                        }
                    }
                }
                self::assertTrue($hasStartDateRangeClause, 'Should have startDate range clause');
                self::assertTrue($hasEndDateBoolClause, 'Should have endDate bool clause');

                return [
                    'hits' => [
                        'hits' => [],
                        'total' => [
                            'value' => 0,
                        ],
                    ],
                    'aggregations' => [
                        'max_start_date' => [
                            'value' => null,
                        ],
                        'max_end_date' => [
                            'value' => null,
                        ],
                        'actors' => [
                            'actor_ids' => [
                                'buckets' => [],
                            ],
                        ],
                        'eventTypes' => [
                            'buckets' => [],
                        ],
                    ],
                ];
            });

        $this->gateway->getEventFacets($watchFileId, $start, $end);
    }

    #[Test]
    public function itBuildsRecentEventsQueryWithCorrectStructure(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $days = 7;

        $this->openSearch
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (array $params) use ($watchFileId) {
                self::assertSame(WatchFileEvent::INDEX_NAME, $params['index']);
                self::assertSame($this->maxEventsLimit, $params['body']['size']);

                // Check query structure
                $query = $params['body']['query'];
                self::assertArrayHasKey('bool', $query);

                // Check must clause (date range)
                self::assertArrayHasKey('must', $query['bool']);
                self::assertCount(1, $query['bool']['must']);
                self::assertArrayHasKey('range', $query['bool']['must'][0]);
                self::assertArrayHasKey('startDate', $query['bool']['must'][0]['range']);
                self::assertSame('now-7d/d', $query['bool']['must'][0]['range']['startDate']['gte']);
                self::assertSame('now/d', $query['bool']['must'][0]['range']['startDate']['lte']);

                // Check filter clause (watchFile.id term)
                self::assertArrayHasKey('filter', $query['bool']);
                self::assertCount(1, $query['bool']['filter']);
                self::assertArrayHasKey('term', $query['bool']['filter'][0]);
                self::assertSame($watchFileId, $query['bool']['filter'][0]['term']['watchFile.id']);

                // Check sort
                self::assertArrayHasKey('sort', $params['body']);
                self::assertCount(1, $params['body']['sort']);
                self::assertArrayHasKey('startDate', $params['body']['sort'][0]);
                self::assertSame('desc', $params['body']['sort'][0]['startDate']['order']);

                return [
                    'hits' => [
                        'hits' => [],
                        'total' => [
                            'value' => 0,
                        ],
                    ],
                ];
            });

        $this->gateway->getRecentEvents($watchFileId, $days);
    }

    #[Test]
    public function itBuildsRecentEventsQueryWithDifferentDays(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $days = 30;

        $this->openSearch
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (array $params) {
                $dateRange = $params['body']['query']['bool']['must'][0]['range']['startDate'];
                self::assertSame('now-30d/d', $dateRange['gte']);
                self::assertSame('now/d', $dateRange['lte']);

                return [
                    'hits' => [
                        'hits' => [],
                        'total' => [
                            'value' => 0,
                        ],
                    ],
                ];
            });

        $this->gateway->getRecentEvents($watchFileId, $days);
    }

    #[Test]
    public function itBuildsQueryWithOnlyStartDate(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $start = new \DateTimeImmutable('2025-10-01T00:00:00+00:00');

        $this->openSearch
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (array $params) {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    $query = $params['body']['query'];
                    self::assertArrayHasKey('bool', $query);

                    // Check date range exists
                    $rangeClause = $query['bool']['must'][1];
                    self::assertArrayHasKey('range', $rangeClause);
                    self::assertArrayHasKey('dateCollect', $rangeClause['range']);
                    self::assertArrayHasKey('gte', $rangeClause['range']['dateCollect']);
                    self::assertArrayNotHasKey('lte', $rangeClause['range']['dateCollect']);

                    // Check extended_bounds
                    $histogram = $params['body']['aggs']['documents_over_time']['date_histogram'];
                    self::assertArrayHasKey('extended_bounds', $histogram);
                    self::assertArrayHasKey('min', $histogram['extended_bounds']);
                    self::assertArrayNotHasKey('max', $histogram['extended_bounds']);
                }

                return $this->createDefaultResponse();
            });

        $this->gateway->getGraphData($watchFileId, '1d', $start, null);
    }

    #[Test]
    public function itBuildsQueryWithOnlyEndDate(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $end = new \DateTimeImmutable('2025-10-31T23:59:59+00:00');

        $this->openSearch
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (array $params) {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    $query = $params['body']['query'];
                    self::assertArrayHasKey('bool', $query);

                    // Check date range exists
                    $rangeClause = $query['bool']['must'][1];
                    self::assertArrayHasKey('range', $rangeClause);
                    self::assertArrayHasKey('dateCollect', $rangeClause['range']);
                    self::assertArrayNotHasKey('gte', $rangeClause['range']['dateCollect']);
                    self::assertArrayHasKey('lte', $rangeClause['range']['dateCollect']);

                    // Check extended_bounds
                    $histogram = $params['body']['aggs']['documents_over_time']['date_histogram'];
                    self::assertArrayHasKey('extended_bounds', $histogram);
                    self::assertArrayNotHasKey('min', $histogram['extended_bounds']);
                    self::assertArrayHasKey('max', $histogram['extended_bounds']);
                }

                return $this->createDefaultResponse();
            });

        $this->gateway->getGraphData($watchFileId, '1d', null, $end);
    }

    #[Test]
    public function itBuildsQueryWithAllIntervals(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $intervals = ['1h', '1d', '1w', '1M', '1y'];

        foreach ($intervals as $interval) {
            $this->setUp(); // Reset mocks

            $this->openSearch
                ->expects($this->exactly(2))
                ->method('search')
                ->willReturnCallback(function (array $params) use ($interval) {
                    static $callCount = 0;
                    ++$callCount;

                    if (1 === $callCount) {
                        $histogram = $params['body']['aggs']['documents_over_time']['date_histogram'];
                        self::assertSame($interval, $histogram['calendar_interval']);
                    }

                    return $this->createDefaultResponse();
                });

            $this->gateway->getGraphData($watchFileId, $interval);
        }
    }

    #[Test]
    public function itRequestsCorrectSourceFieldsWithoutActorFilter(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';

        $this->openSearch
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (array $params) {
                static $callCount = 0;
                ++$callCount;

                if (2 === $callCount) {
                    // Events query should request startDate and endDate only (no documentLinks)
                    self::assertSame(['startDate', 'endDate'], $params['body']['_source']);
                }

                return $this->createDefaultResponse();
            });

        $this->gateway->getGraphData($watchFileId, '1d', null, null, []);
    }

    #[Test]
    public function itRequestsDocumentLinksWithActorFilter(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $actorIds = ['actor-1'];

        $this->openSearch
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (array $params) {
                // Events query should request startDate, endDate, AND documentLinks
                self::assertContains('startDate', $params['body']['_source']);
                self::assertContains('endDate', $params['body']['_source']);
                self::assertContains('documentLinks', $params['body']['_source']);

                return $this->createDefaultResponse();
            });

        $this->gateway->getGraphData($watchFileId, '1d', null, null, $actorIds);
    }

    /**
     * Helper method to create a default response array.
     *
     * @return array<string, mixed>
     */
    private function createDefaultResponse(): array
    {
        return [
            'hits' => [
                'hits' => [],
                'total' => [
                    'value' => 0,
                ],
            ],
            'aggregations' => [
                'documents_over_time' => [
                    'buckets' => [],
                ],
            ],
        ];
    }
}
