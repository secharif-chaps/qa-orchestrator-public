<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenSearch\Filter;

use ApiPlatform\Elasticsearch\Filter\AbstractFilter;
use ApiPlatform\Elasticsearch\Filter\ConstantScoreFilterInterface;
use ApiPlatform\Elasticsearch\Filter\FilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Domain\WatchFileEvent\WatchFileEvent;

/**
 * Custom filter to handle filtering on nested actors field.
 * This filter properly handles the nested structure of actors in OpenSearch.
 */
class NestedActorFilter extends AbstractFilter implements FilterInterface, ConstantScoreFilterInterface
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

        if (WatchFileEvent::class !== $resourceClass || !isset($filters['actors.id'])) {
            return $clauseBody;
        }

        $actorIds = $filters['actors.id'];

        // Normalize to array for consistent handling
        if (\is_string($actorIds)) {
            if ('' === trim($actorIds)) {
                return $clauseBody;
            }
            $actorIds = [$actorIds];
        } elseif (!\is_array($actorIds)) {
            return $clauseBody;
        }

        // Filter out empty strings
        $actorIds = array_filter($actorIds, fn ($id) => \is_string($id) && '' !== trim($id));

        if (empty($actorIds)) {
            return $clauseBody;
        }

        // Use 'term' for single value, 'terms' for multiple values
        $termQuery = 1 === \count($actorIds)
            ? [
                'term' => [
                    'actors.id' => reset($actorIds),
                ],
            ]
            : [
                'terms' => [
                    'actors.id' => array_values($actorIds),
                ],
            ];

        // Build nested query for actors.id
        $nestedQuery = [
            'nested' => [
                'path' => 'actors',
                'query' => $termQuery,
            ],
        ];

        // Add the nested query to the must clause
        if (isset($clauseBody['bool']) && \is_array($clauseBody['bool'])
            && isset($clauseBody['bool']['must']) && \is_array($clauseBody['bool']['must'])) {
            $clauseBody['bool']['must'][] = $nestedQuery;
        }

        return $clauseBody;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDescription(string $resourceClass): array
    {
        return [
            'actors.id' => [
                'property' => 'actors.id',
                'type' => 'string',
                'required' => false,
                'openapi' => new Parameter(
                    'actors.id',
                    'query',
                    'Filter events by actor ID. Only events containing the specified actor will be returned.',
                    schema: [
                        'type' => 'string',
                    ],
                ),
            ],
            'actors.id[]' => [
                'property' => 'actors.id',
                'type' => 'array',
                'required' => false,
                'openapi' => new Parameter(
                    'actors.id[]',
                    'query',
                    'Filter events by multiple actor IDs. Only events containing at least one of the specified actors will be returned.',
                    explode: true,
                    schema: [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                    ],
                ),
            ],
        ];
    }
}
