<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenSearch\Query;

/**
 * Builder for OpenSearch queries that filter events by date range overlap.
 * An event overlaps with a given date range if:
 * - event.startDate <= filter.endDate (event starts before or during the range)
 * - AND (event.endDate >= filter.startDate OR event.endDate is null/ongoing).
 */
readonly class EventDateRangeBuilder
{
    /**
     * Build OpenSearch query clauses for event date range overlap.
     *
     * @param \DateTimeImmutable|string|null $startDate Filter start date (events must end after or be ongoing)
     * @param \DateTimeImmutable|string|null $endDate   Filter end date (events must start before)
     *
     * @return list<array{range: array<string, array<string, string>>}|array{bool: array<string, mixed>}> Array of OpenSearch query clauses
     */
    public function buildClauses(
        \DateTimeImmutable|string|null $startDate = null,
        \DateTimeImmutable|string|null $endDate = null,
    ): array {
        $clauses = [];

        // If filter has endDate: event.startDate <= filter.endDate
        if (null !== $endDate) {
            $formattedEndDate = $this->formatDateTime($endDate);
            $clauses[] = [
                'range' => [
                    'startDate' => [
                        'lte' => $formattedEndDate,
                    ],
                ],
            ];
        }

        // If filter has startDate: event.endDate >= filter.startDate OR event.endDate is null
        if (null !== $startDate) {
            $formattedStartDate = $this->formatDateTime($startDate);
            $clauses[] = [
                'bool' => [
                    'should' => [
                        // Either event endDate is >= filter startDate
                        [
                            'range' => [
                                'endDate' => [
                                    'gte' => $formattedStartDate,
                                ],
                            ],
                        ],
                        // OR event endDate doesn't exist (ongoing event)
                        [
                            'bool' => [
                                'must_not' => [
                                    'exists' => [
                                        'field' => 'endDate',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'minimum_should_match' => 1,
                ],
            ];
        }

        return $clauses;
    }

    /**
     * Format a date/time value to OpenSearch format.
     */
    private function formatDateTime(\DateTimeImmutable|string $dateTime): string
    {
        if ($dateTime instanceof \DateTimeImmutable) {
            return $dateTime->format('Y-m-d\TH:i:s\Z');
        }

        // Try to parse the string date and format it for OpenSearch
        try {
            $dt = new \DateTimeImmutable($dateTime);

            return $dt->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception) {
            throw new \InvalidArgumentException('Input date time has invalid format');
        }
    }
}
