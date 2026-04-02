<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenSearch\Extension;

use ApiPlatform\Elasticsearch\Extension\RequestBodySearchCollectionExtensionInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\WatchFileEvent\WatchFileEvent;
use Webmozart\Assert\Assert;

class WatchFileEventFacetsCollectionExtension implements RequestBodySearchCollectionExtensionInterface
{
    private const int MAX_ACTORS_AGGREGATION_SIZE = 1000;
    private const int MAX_EVENT_TYPES_AGGREGATION_SIZE = 20;

    /**
     * @param array<string, mixed> $requestBody
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function applyToCollection(
        array $requestBody,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): array {
        if (WatchFileEvent::class !== $resourceClass) {
            return $requestBody;
        }

        // Extract filtered values from context
        $filters = $context['filters'] ?? [];
        if (!\is_array($filters)) {
            $filters = [];
        }
        $filteredActorIds = $this->extractFilterValues($filters, 'actors.id');
        $filteredEventTypes = $this->extractFilterValues($filters, 'eventType');

        // Build actors aggregation
        $actorsTermsConfig = [
            'field' => 'actors.id',
            'size' => self::MAX_ACTORS_AGGREGATION_SIZE,
        ];
        if (!empty($filteredActorIds)) {
            $actorsTermsConfig['include'] = $filteredActorIds;
            $actorsTermsConfig['min_doc_count'] = 0;
        }

        // Build event types aggregation
        $eventTypesTermsConfig = [
            'field' => 'eventType',
            'size' => self::MAX_EVENT_TYPES_AGGREGATION_SIZE,
        ];
        if (!empty($filteredEventTypes)) {
            $eventTypesTermsConfig['include'] = $filteredEventTypes;
            $eventTypesTermsConfig['min_doc_count'] = 0;
        }

        // Initialize aggregations if not already set
        if (!isset($requestBody['aggs'])) {
            $requestBody['aggs'] = [];
        }

        Assert::isArray($requestBody['aggs'], 'Aggregations must be an array');

        // Add actors aggregation (nested field)
        $requestBody['aggs']['actors'] = [
            'nested' => [
                'path' => 'actors',
            ],
            'aggs' => [
                'actor_ids' => [
                    'terms' => $actorsTermsConfig,
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
        $requestBody['aggs']['eventTypes'] = [
            'terms' => $eventTypesTermsConfig,
        ];

        // Add date range aggregations
        $requestBody['aggs']['max_start_date'] = [
            'max' => [
                'field' => 'startDate',
            ],
        ];

        $requestBody['aggs']['max_end_date'] = [
            'max' => [
                'field' => 'endDate',
            ],
        ];

        return $requestBody;
    }

    /**
     * Extract filter values from the filters array.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<string>
     */
    private function extractFilterValues(array $filters, string $filterKey): array
    {
        if (!isset($filters[$filterKey])) {
            return [];
        }

        $values = $filters[$filterKey];

        if (\is_array($values)) {
            $result = [];
            foreach ($values as $value) {
                if (\is_string($value) && '' !== trim($value)) {
                    $result[] = $value;
                }
            }

            return $result;
        }

        if (\is_string($values) && '' !== trim($values)) {
            return [$values];
        }

        return [];
    }
}
