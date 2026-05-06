<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFileEvent;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFileEvent\WatchFileEventGatewayInterface;
use App\Infrastructure\Pagination\WatchFileEventPaginatorWithAggregations;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\UserInterface\Dto\WatchFileEvent\EventGraphEntryDto;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<WatchFileEventPaginatorWithAggregations>
 */
readonly class EventsGraphProvider implements ProviderInterface
{
    public function __construct(
        private WatchFileEventGatewayInterface $watchFileEventGateway,
        private WatchFileGatewayInterface $watchFileGateway,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        Assert::keyExists($uriVariables, 'watchFileId', 'Watch file ID is required.');
        Assert::stringNotEmpty($uriVariables['watchFileId'], 'Watch file ID must be a non-empty string.');

        $watchFileId = trim((string) $uriVariables['watchFileId']);
        Assert::uuid($watchFileId, 'Watch file ID must be a valid UUID.');

        // Verify watchfile exists and user has access
        $watchFile = $this->watchFileGateway->get($watchFileId);

        if (!$this->security->isGranted(WatchFileVoter::VIEW, $watchFile)) {
            throw new AccessDeniedException('You do not have permission to view events for this watchfile.');
        }

        // Get interval from query parameters (default to '1d' for daily)
        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];
        $interval = $filters['interval'] ?? '1d';
        Assert::string($interval, 'Interval must be a string.');

        // Validate interval format
        $validIntervals = ['1h', '1d', '1w', '1M', '1y'];
        if (!\in_array($interval, $validIntervals, true)) {
            $interval = '1d'; // Default to daily if invalid
        }

        // Get startDate and endDate parameters
        $start = null;
        $end = null;

        if (isset($filters['startDate'])) {
            $startValue = $filters['startDate'];
            if (\is_string($startValue)) {
                try {
                    $start = new \DateTimeImmutable($startValue);
                } catch (\Exception $e) {
                    // Invalid date format, ignore
                }
            }
        }

        if (isset($filters['endDate'])) {
            $endValue = $filters['endDate'];
            if (\is_string($endValue)) {
                try {
                    $end = new \DateTimeImmutable($endValue);
                } catch (\Exception $e) {
                    // Invalid date format, ignore
                }
            }
        }

        // Get actor IDs filter
        $actorIds = [];
        if (isset($filters['actors.id'])) {
            $actorValue = $filters['actors.id'];
            if (\is_array($actorValue)) {
                $actorIds = array_values(array_filter($actorValue, 'is_string'));
            } elseif (\is_string($actorValue)) {
                $actorIds = [$actorValue];
            }
        }

        // Get event types filter
        $eventTypes = [];
        if (isset($filters['eventType'])) {
            $eventTypeValue = $filters['eventType'];
            if (\is_array($eventTypeValue)) {
                $eventTypes = array_values(array_filter($eventTypeValue, 'is_string'));
            } elseif (\is_string($eventTypeValue)) {
                $eventTypes = [$eventTypeValue];
            }
        }

        // Get graph data from OpenSearch
        $graphData = $this->watchFileEventGateway->getGraphData(
            $watchFileId,
            $interval,
            $start,
            $end,
            $actorIds,
            $eventTypes
        );

        // Get facets from OpenSearch (includes actors, maxStartDate, maxEndDate)
        $facets = $this->watchFileEventGateway->getEventFacets($watchFileId, $start, $end, $actorIds, $eventTypes);

        // Transform to DTOs
        $result = [];
        foreach ($graphData as $entry) {
            $entryStart = new \DateTimeImmutable($entry['start']);
            $entryEnd = new \DateTimeImmutable($entry['end']);

            // Build link to events collection filtered by this entry's timeframe
            $link = $this->buildEventLink($watchFileId, $entryStart, $entryEnd, $actorIds);

            $result[] = new EventGraphEntryDto(
                $entry['documentsCount'],
                $entry['eventsCount'],
                $entry['hasEvents'],
                $entryStart,
                $entryEnd,
                $link,
            );
        }

        /** @var \ApiPlatform\State\Pagination\PaginatorInterface<EventGraphEntryDto> $pager */
        $pager = new ArrayPaginator($result, 0, \count($result));

        return new WatchFileEventPaginatorWithAggregations($pager, $facets);
    }

    /**
     * Build a link to the events collection filtered by timeframe and optional actors.
     *
     * @param array<int, string> $actorIds
     */
    private function buildEventLink(
        string $watchFileId,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        array $actorIds,
    ): string {
        $routeParams = [
            'watchFileId' => $watchFileId,
            'startDate' => $start->format('c'),
            'endDate' => $end->format('c'),
            'order' => [
                'startDate' => 'desc',
            ],
        ];

        // Add actor filters if present
        if (!empty($actorIds)) {
            $routeParams['actors.id'] = $actorIds;
        }

        return $this->urlGenerator->generate(
            'get_watch_file_events',
            $routeParams,
            UrlGeneratorInterface::ABSOLUTE_PATH
        );
    }
}
