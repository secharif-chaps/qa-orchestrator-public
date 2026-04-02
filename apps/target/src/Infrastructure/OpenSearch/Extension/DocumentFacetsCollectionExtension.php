<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenSearch\Extension;

use ApiPlatform\Elasticsearch\Extension\RequestBodySearchCollectionExtensionInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\Document\Document;
use Webmozart\Assert\Assert;

class DocumentFacetsCollectionExtension implements RequestBodySearchCollectionExtensionInterface
{
    private const int MAX_FACET_SIZE = 100;

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
        // Only apply to Document resources
        if (Document::class !== $resourceClass) {
            return $requestBody;
        }

        // Extract filtered values from context
        $filters = $context['filters'] ?? [];
        if (!\is_array($filters)) {
            $filters = [];
        }
        $filteredActorIds = $this->extractFilterValues($filters, 'actor.id', true);
        $filteredSourceIds = $this->extractFilterValues($filters, 'source.id', true);

        // Build actors aggregation
        $actorsTermsConfig = [
            'field' => 'actor.id',
            'size' => self::MAX_FACET_SIZE,
        ];
        if (!empty($filteredActorIds)) {
            $actorsTermsConfig['include'] = $filteredActorIds;
            $actorsTermsConfig['min_doc_count'] = 0;
        }

        // Build sources aggregation
        $sourcesTermsConfig = [
            'field' => 'source.id',
            'size' => self::MAX_FACET_SIZE,
        ];
        if (!empty($filteredSourceIds)) {
            $sourcesTermsConfig['include'] = $filteredSourceIds;
            $sourcesTermsConfig['min_doc_count'] = 0;
        }

        $requestBody['aggs'] = [
            'domains' => [
                'terms' => [
                    'field' => 'actor.primaryDomain.keyword',
                    'size' => self::MAX_FACET_SIZE,
                    'missing' => 'empty',
                ],
            ],
            'sources' => [
                'terms' => $sourcesTermsConfig,
                'aggs' => [
                    'source_name' => [
                        'top_hits' => [
                            '_source' => ['source.name'],
                            'size' => 1,
                        ],
                    ],
                    'source_primary_domain' => [
                        'top_hits' => [
                            '_source' => ['source.primaryDomain'],
                            'size' => 1,
                        ],
                    ],
                ],
            ],
            'actors' => [
                'terms' => $actorsTermsConfig,
                'aggs' => [
                    'actor_label' => [
                        'top_hits' => [
                            '_source' => ['actor.label'],
                            'size' => 1,
                        ],
                    ],
                    'actor_primary_domain' => [
                        'top_hits' => [
                            '_source' => ['actor.primaryDomain'],
                            'size' => 1,
                        ],
                    ],
                ],
            ],
            'statuses' => [
                'terms' => [
                    'field' => 'status.keyword',
                    'size' => self::MAX_FACET_SIZE,
                ],
            ],
            'aiValidationStatuses' => [
                'terms' => [
                    'field' => 'aiValidation.status',
                    'size' => self::MAX_FACET_SIZE,
                    'missing' => 'empty',
                ],
            ],
            'manualValidationStatuses' => [
                'terms' => [
                    'field' => 'manualStatus',
                    'size' => self::MAX_FACET_SIZE,
                    'missing' => 'empty',
                ],
            ],
        ];

        return $requestBody;
    }

    /**
     * Extract filter values from the filters array.
     * Can validate UUIDs for ID-based filters (e.g., actor.id, source.id) or handle string values.
     *
     * @param array<string, mixed> $filters
     * @param bool                 $validateUuid If true, validates that values are valid UUIDs. If false, accepts any non-empty string.
     *
     * @return array<string>
     */
    private function extractFilterValues(array $filters, string $filterKey, bool $validateUuid = true): array
    {
        if (!isset($filters[$filterKey])) {
            return [];
        }

        $values = $filters[$filterKey];

        if (\is_array($values)) {
            $result = [];
            foreach ($values as $value) {
                if (!\is_string($value)) {
                    continue;
                }

                if ($validateUuid) {
                    try {
                        Assert::uuid($value);
                        $result[] = $value;
                    } catch (\InvalidArgumentException) {
                        continue;
                    }
                } else {
                    $trimmed = trim($value);
                    if ('' !== $trimmed) {
                        $result[] = $trimmed;
                    }
                }
            }

            return $result;
        }

        if (\is_string($values)) {
            if ($validateUuid) {
                try {
                    Assert::uuid($values);

                    return [$values];
                } catch (\InvalidArgumentException) {
                    return [];
                }
            } else {
                $trimmed = trim($values);
                if ('' !== $trimmed) {
                    return [$trimmed];
                }
            }
        }

        return [];
    }
}
