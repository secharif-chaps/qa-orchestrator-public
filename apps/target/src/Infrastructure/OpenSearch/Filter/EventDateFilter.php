<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenSearch\Filter;

use ApiPlatform\Elasticsearch\Filter\AbstractFilter;
use ApiPlatform\Elasticsearch\Filter\ConstantScoreFilterInterface;
use ApiPlatform\Elasticsearch\Filter\FilterInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Infrastructure\OpenSearch\Query\EventDateRangeBuilder;

/**
 * Custom filter to find events that overlap with a given date range.
 * An event overlaps with the filter range if:
 * - event.startDate < filter.endDate (or <= filter.endDate)
 * - AND (event.endDate > filter.startDate OR event.endDate is null/ongoing).
 */
class EventDateFilter extends AbstractFilter implements FilterInterface, ConstantScoreFilterInterface
{
    public function __construct(
        private readonly EventDateRangeBuilder $dateRangeBuilder,
    ) {
    }

    /**
     * @param array<string, mixed> $clauseBody
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function apply(
        array $clauseBody,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): array {
        /** @var array<string, mixed> */
        $filters = $context['filters'] ?? [];

        // Check if either 'startDate' or 'endDate' filter is provided
        $hasStartDate = isset($filters['startDate']) && \is_string($filters['startDate']);
        $hasEndDate = isset($filters['endDate']) && \is_string($filters['endDate']);

        if (WatchFileEvent::class !== $resourceClass || (!$hasStartDate && !$hasEndDate)) {
            return $clauseBody;
        }

        /** @var string|null $startDateValue */
        $startDateValue = $hasStartDate ? $filters['startDate'] : null;
        /** @var string|null $endDateValue */
        $endDateValue = $hasEndDate ? $filters['endDate'] : null;

        // Build date range clauses using the shared builder
        $mustClauses = $this->dateRangeBuilder->buildClauses($startDateValue, $endDateValue);

        // Add all must clauses to the query
        if (\is_array($clauseBody['bool']) && \is_array($clauseBody['bool']['must'])) {
            foreach ($mustClauses as $clause) {
                $clauseBody['bool']['must'][] = $clause;
            }
        }

        return $clauseBody;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDescription(string $resourceClass): array
    {
        return [
            'startDate' => [
                'property' => 'startDate',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter events that overlap with a date range starting from this date. Returns events where endDate >= startDate or endDate is null (ongoing). Use ISO 8601 format (e.g., 2024-01-15 or 2024-01-15T10:30:00Z).',
            ],
            'endDate' => [
                'property' => 'endDate',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter events that overlap with a date range ending at this date. Returns events where startDate <= endDate. Use ISO 8601 format (e.g., 2024-01-15 or 2024-01-15T10:30:00Z).',
            ],
        ];
    }
}
