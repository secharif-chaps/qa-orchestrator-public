<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenSearch\Filter;

use ApiPlatform\Elasticsearch\Filter\AbstractFilter;
use ApiPlatform\Elasticsearch\Filter\ConstantScoreFilterInterface;
use ApiPlatform\Elasticsearch\Filter\FilterInterface;
use ApiPlatform\Metadata\Operation;

class DateTimeFilter extends AbstractFilter implements FilterInterface, ConstantScoreFilterInterface
{
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

        foreach ($this->properties ?? [] as $property => $strategy) {
            if (\is_string($property)) {
                $rangeClause = $this->buildRangeClause($property, $filters);
                if ($rangeClause && \is_array($clauseBody['bool']) && \is_array($clauseBody['bool']['must'])) {
                    $clauseBody['bool']['must'][] = [
                        'range' => [
                            $property => $rangeClause,
                        ],
                    ];
                }
            }
        }

        return $clauseBody;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>|null
     */
    private function buildRangeClause(string $property, array $filters): ?array
    {
        $range = [];

        if (!isset($filters[$property]) || !\is_array($filters[$property])) {
            return null;
        }

        // Check for 'after' parameter (greater than or equal)
        if (isset($filters[$property]['after'])) {
            $value = $filters[$property]['after'];
            if (\is_string($value)) {
                $range['gte'] = $this->formatDateTime($value);
            }
        }

        // Check for 'before' parameter (less than or equal)
        if (isset($filters[$property]['before'])) {
            $value = $filters[$property]['before'];
            if (\is_string($value)) {
                $range['lte'] = $this->formatDateTime($value);
            }
        }

        // Check for 'strictly_after' parameter (greater than)
        if (isset($filters[$property]['strictly_after'])) {
            $value = $filters[$property]['strictly_after'];
            if (\is_string($value)) {
                $range['gt'] = $this->formatDateTime($value);
            }
        }

        // Check for 'strictly_before' parameter (less than)
        if (isset($filters[$property]['strictly_before'])) {
            $value = $filters[$property]['strictly_before'];
            if (\is_string($value)) {
                $range['lt'] = $this->formatDateTime($value);
            }
        }

        return !empty($range) ? $range : null;
    }

    private function formatDateTime(string $dateTime): string
    {
        // Try to parse the date and format it for OpenSearch
        try {
            $dt = new \DateTimeImmutable($dateTime);

            return $dt->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception $e) {
            // If parsing fails, return as-is (might be a valid OpenSearch date format)
            return $dateTime;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->properties ?? [] as $property => $strategy) {
            $description["{$property}[after]"] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
                'description' => "Filter {$property} after the given date (inclusive). Use ISO 8601 format.",
            ];

            $description["{$property}[before]"] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
                'description' => "Filter {$property} before the given date (inclusive). Use ISO 8601 format.",
            ];

            $description["{$property}[strictly_after]"] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
                'description' => "Filter {$property} strictly after the given date (exclusive). Use ISO 8601 format.",
            ];

            $description["{$property}[strictly_before]"] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
                'description' => "Filter {$property} strictly before the given date (exclusive). Use ISO 8601 format.",
            ];
        }

        return $description;
    }
}
