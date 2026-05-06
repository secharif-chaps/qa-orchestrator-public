<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFileEvent;

use ApiPlatform\Metadata\Operation;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Pagination\WatchFileEventPaginatorWithAggregations;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\Infrastructure\WatchFileEvent\EventsGraphProvider;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileEventGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\UserInterface\Dto\WatchFileEvent\EventGraphEntryDto;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Webmozart\Assert\InvalidArgumentException;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(EventsGraphProvider::class)]
final class EventsGraphProviderTest extends TestCase
{
    private NullWatchFileGateway $watchFileGateway;
    private Security&MockObject $security;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private Operation $operation;
    private EventsGraphProvider $provider;
    private NullWatchFileEventGateway&MockObject $watchFileEventGateway;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->watchFileEventGateway = $this->createMock(NullWatchFileEventGateway::class);
        $this->security = $this->createMock(Security::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->operation = $this->createStub(Operation::class);

        $this->provider = new EventsGraphProvider(
            $this->watchFileEventGateway,
            $this->watchFileGateway,
            $this->security,
            $this->urlGenerator,
        );
    }

    #[Test]
    public function itThrowsExceptionWhenWatchFileIdIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Watch file ID is required');

        $this->provider->provide($this->operation, [], []);
    }

    #[Test]
    public function itThrowsExceptionWhenWatchFileIdIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Watch file ID must be a non-empty string');

        $this->provider->provide($this->operation, [
            'watchFileId' => '',
        ], []);
    }

    #[Test]
    public function itThrowsExceptionWhenWatchFileIdIsNotValidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Watch file ID must be a valid UUID');

        $this->provider->provide($this->operation, [
            'watchFileId' => 'invalid-uuid',
        ], []);
    }

    #[Test]
    public function itThrowsAccessDeniedExceptionWhenUserDoesNotHavePermission(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to view events for this watchfile');

        $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], []);
    }

    #[Test]
    public function itProvidesGraphDataWithDefaultInterval(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $graphData = [
            [
                'documentsCount' => 10,
                'eventsCount' => 5,
                'hasEvents' => true,
                'start' => '2025-10-01T00:00:00+00:00',
                'end' => '2025-10-02T00:00:00+00:00',
            ],
            [
                'documentsCount' => 8,
                'eventsCount' => 3,
                'hasEvents' => true,
                'start' => '2025-10-02T00:00:00+00:00',
                'end' => '2025-10-03T00:00:00+00:00',
            ],
        ];

        $facets = [
            'actors' => [],
            'eventTypes' => [],
            'maxStartDate' => '2025-10-01T00:00:00+00:00',
            'maxEndDate' => '2025-10-03T00:00:00+00:00',
        ];

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getGraphData')
            ->with($watchFileId, '1d', null, null, [])
            ->willReturn($graphData);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getEventFacets')
            ->with($watchFileId, null, null, [])
            ->willReturn($facets);

        $this->urlGenerator
            ->expects($this->exactly(2))
            ->method('generate')
            ->with(
                'get_watch_file_events',
                $this->callback(function ($params) {
                    return isset($params['watchFileId'])
                        && isset($params['startDate'])
                        && isset($params['endDate'])
                        && !isset($params['actors.id']);
                }),
                UrlGeneratorInterface::ABSOLUTE_PATH
            )
            ->willReturn('/api/watch-files/' . $watchFileId . '/events');

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        self::assertInstanceOf(WatchFileEventPaginatorWithAggregations::class, $result);
        self::assertSame(2, $result->count());
        self::assertSame($facets, $result->aggregations);

        $items = iterator_to_array($result);
        self::assertCount(2, $items);

        /** @var EventGraphEntryDto $firstEntry */
        $firstEntry = $items[0];
        self::assertSame(10, $firstEntry->documentsCount);
        self::assertSame(5, $firstEntry->eventsCount);
        self::assertTrue($firstEntry->hasEvents);
        self::assertEquals(new \DateTimeImmutable('2025-10-01T00:00:00+00:00'), $firstEntry->start);
        self::assertEquals(new \DateTimeImmutable('2025-10-02T00:00:00+00:00'), $firstEntry->end);
    }

    #[Test]
    public function itProvidesGraphDataWithCustomInterval(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getGraphData')
            ->with($watchFileId, '1w', null, null, [])
            ->willReturn([]);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getEventFacets')
            ->with($watchFileId, null, null, [])
            ->willReturn([
                'actors' => [],
                'eventTypes' => [],
                'maxStartDate' => null,
                'maxEndDate' => null,
            ]);

        $context = [
            'filters' => [
                'interval' => '1w',
            ],
        ];
        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], $context);

        self::assertInstanceOf(WatchFileEventPaginatorWithAggregations::class, $result);
    }

    #[Test]
    public function itDefaultsToOneDayIntervalWhenInvalid(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getGraphData')
            ->with($watchFileId, '1d', null, null, [])
            ->willReturn([]);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getEventFacets')
            ->willReturn([
                'actors' => [],
                'eventTypes' => [],
                'maxStartDate' => null,
                'maxEndDate' => null,
            ]);

        $context = [
            'filters' => [
                'interval' => 'invalid',
            ],
        ];
        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], $context);

        self::assertInstanceOf(WatchFileEventPaginatorWithAggregations::class, $result);
    }

    #[Test]
    public function itProvidesGraphDataWithStartAndEndDateFilters(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);
        $start = new \DateTimeImmutable('2025-10-01T00:00:00+00:00');
        $end = new \DateTimeImmutable('2025-10-31T23:59:59+00:00');

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getGraphData')
            ->with($watchFileId, '1d', $start, $end, [])
            ->willReturn([]);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getEventFacets')
            ->with($watchFileId, $start, $end, [])
            ->willReturn([
                'actors' => [],
                'eventTypes' => [],
                'maxStartDate' => null,
                'maxEndDate' => null,
            ]);

        $context = [
            'filters' => [
                'startDate' => '2025-10-01T00:00:00+00:00',
                'endDate' => '2025-10-31T23:59:59+00:00',
            ],
        ];

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], $context);

        self::assertInstanceOf(WatchFileEventPaginatorWithAggregations::class, $result);
    }

    #[Test]
    public function itIgnoresInvalidDateFormats(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getGraphData')
            ->with($watchFileId, '1d', null, null, [])
            ->willReturn([]);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getEventFacets')
            ->with($watchFileId, null, null, [])
            ->willReturn([
                'actors' => [],
                'eventTypes' => [],
                'maxStartDate' => null,
                'maxEndDate' => null,
            ]);

        $context = [
            'filters' => [
                'startDate' => 'invalid-date',
                'endDate' => 'invalid-date',
            ],
        ];

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], $context);

        self::assertInstanceOf(WatchFileEventPaginatorWithAggregations::class, $result);
    }

    #[Test]
    public function itProvidesGraphDataWithSingleActorFilter(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);
        $actorId = '660e8400-e29b-41d4-a716-446655440000';

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getGraphData')
            ->with($watchFileId, '1d', null, null, [$actorId])
            ->willReturn([]);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getEventFacets')
            ->with($watchFileId, null, null, [$actorId])
            ->willReturn([
                'actors' => [],
                'eventTypes' => [],
                'maxStartDate' => null,
                'maxEndDate' => null,
            ]);

        $context = [
            'filters' => [
                'actors.id' => $actorId,
            ],
        ];
        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], $context);

        self::assertInstanceOf(WatchFileEventPaginatorWithAggregations::class, $result);
    }

    #[Test]
    public function itProvidesGraphDataWithMultipleActorFilters(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);
        $actorIds = ['660e8400-e29b-41d4-a716-446655440001', '660e8400-e29b-41d4-a716-446655440002'];

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getGraphData')
            ->with($watchFileId, '1d', null, null, $actorIds)
            ->willReturn([]);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getEventFacets')
            ->with($watchFileId, null, null, $actorIds)
            ->willReturn([
                'actors' => [],
                'eventTypes' => [],
                'maxStartDate' => null,
                'maxEndDate' => null,
            ]);

        $context = [
            'filters' => [
                'actors.id' => $actorIds,
            ],
        ];
        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], $context);

        self::assertInstanceOf(WatchFileEventPaginatorWithAggregations::class, $result);
    }

    #[Test]
    public function itBuildsLinkWithActorFilters(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);
        $actorIds = ['660e8400-e29b-41d4-a716-446655440001', '660e8400-e29b-41d4-a716-446655440002'];

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $graphData = [
            [
                'documentsCount' => 5,
                'eventsCount' => 2,
                'hasEvents' => true,
                'start' => '2025-10-01T00:00:00+00:00',
                'end' => '2025-10-02T00:00:00+00:00',
            ],
        ];

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getGraphData')
            ->willReturn($graphData);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getEventFacets')
            ->willReturn([
                'actors' => [],
                'eventTypes' => [],
                'maxStartDate' => null,
                'maxEndDate' => null,
            ]);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with(
                'get_watch_file_events',
                $this->callback(function ($params) use ($actorIds) {
                    return isset($params['actors.id'])
                        && $params['actors.id'] === $actorIds;
                }),
                UrlGeneratorInterface::ABSOLUTE_PATH
            )
            ->willReturn('/api/watch-files/' . $watchFileId . '/events?actors.id=' . implode(',', $actorIds));

        $context = [
            'filters' => [
                'actors.id' => $actorIds,
            ],
        ];
        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], $context);

        self::assertInstanceOf(WatchFileEventPaginatorWithAggregations::class, $result);

        $items = iterator_to_array($result);
        /** @var EventGraphEntryDto $firstEntry */
        $firstEntry = $items[0];
        self::assertStringContainsString('actors.id', $firstEntry->link);
    }

    #[Test]
    public function itTrimsWatchFileId(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getGraphData')
            ->with($watchFileId, '1d', null, null, [])
            ->willReturn([]);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getEventFacets')
            ->willReturn([
                'actors' => [],
                'eventTypes' => [],
                'maxStartDate' => null,
                'maxEndDate' => null,
            ]);

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => '  ' . $watchFileId . '  ',
        ], []);

        self::assertInstanceOf(WatchFileEventPaginatorWithAggregations::class, $result);
    }

    #[Test]
    public function itFiltersOutNonStringActorIds(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);
        $validActorId = '660e8400-e29b-41d4-a716-446655440001';

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        // Note: empty strings pass is_string() so they will be included
        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getGraphData')
            ->with($watchFileId, '1d', null, null, [$validActorId, ''])
            ->willReturn([]);

        $this->watchFileEventGateway
            ->expects($this->once())
            ->method('getEventFacets')
            ->with($watchFileId, null, null, [$validActorId, ''])
            ->willReturn([
                'actors' => [],
                'eventTypes' => [],
                'maxStartDate' => null,
                'maxEndDate' => null,
            ]);

        $context = [
            'filters' => [
                'actors.id' => [$validActorId, 123, null, ''],
            ],
        ];
        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], $context);

        self::assertInstanceOf(WatchFileEventPaginatorWithAggregations::class, $result);
    }

    #[Test]
    public function itHandlesAllValidIntervals(): void
    {
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->createWatchFile($watchFileId);
        $validIntervals = ['1h', '1d', '1w', '1M', '1y'];

        foreach ($validIntervals as $interval) {
            $this->setUp(); // Reset mocks for each iteration
            $watchFile = $this->createWatchFile($watchFileId);

            $this->security
                ->expects($this->once())
                ->method('isGranted')
                ->with(WatchFileVoter::VIEW, $watchFile)
                ->willReturn(true);

            $this->watchFileEventGateway
                ->expects($this->once())
                ->method('getGraphData')
                ->with($watchFileId, $interval, null, null, [])
                ->willReturn([]);

            $this->watchFileEventGateway
                ->expects($this->once())
                ->method('getEventFacets')
                ->willReturn([
                    'actors' => [],
                    'maxStartDate' => null,
                    'maxEndDate' => null,
                ]);

            $context = [
                'filters' => [
                    'interval' => $interval,
                ],
            ];
            $result = $this->provider->provide($this->operation, [
                'watchFileId' => $watchFileId,
            ], $context);

            self::assertInstanceOf(WatchFileEventPaginatorWithAggregations::class, $result);
        }
    }

    private function createWatchFile(string $watchFileId): WatchFile
    {
        $watchFile = new WatchFile('Test Watchfile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));

        // Use reflection to set the ID
        $reflection = new \ReflectionClass($watchFile);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($watchFile, $watchFileId);

        $this->watchFileGateway->save($watchFile);

        return $watchFile;
    }
}
