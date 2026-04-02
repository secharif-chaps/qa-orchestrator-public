<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenSearch\Filter;

use ApiPlatform\Elasticsearch\Filter\AbstractFilter;
use ApiPlatform\Elasticsearch\Filter\ConstantScoreFilterInterface;
use ApiPlatform\Elasticsearch\Filter\FilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Domain\WatchFileEvent\EventType;
use App\Domain\WatchFileEvent\WatchFileEvent;

class EventTypeFilter extends AbstractFilter implements FilterInterface, ConstantScoreFilterInterface
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
        if (WatchFileEvent::class !== $resourceClass) {
            return $clauseBody;
        }

        $filters = $context['filters'] ?? [];

        if (!\is_array($filters) || !isset($filters['eventType'])) {
            return $clauseBody;
        }

        $eventTypes = $filters['eventType'];

        // Normalize to array for consistent handling
        if (\is_string($eventTypes)) {
            if ('' === trim($eventTypes)) {
                return $clauseBody;
            }
            $eventTypes = [$eventTypes];
        } elseif (!\is_array($eventTypes)) {
            return $clauseBody;
        }

        // Filter out empty strings and validate against enum
        $validEventTypes = [];
        foreach ($eventTypes as $type) {
            if (!\is_string($type) || '' === trim($type)) {
                continue;
            }

            $trimmedType = trim($type);
            // Validate against enum - skip invalid values
            try {
                EventType::from($trimmedType);
                $validEventTypes[] = $trimmedType;
            } catch (\ValueError) {
                // Skip invalid event types
                continue;
            }
        }

        if (empty($validEventTypes)) {
            return $clauseBody;
        }

        $eventTypes = $validEventTypes;

        if (!isset($clauseBody['bool'])) {
            $clauseBody['bool'] = [];
        }
        if (!\is_array($clauseBody['bool'])) {
            $clauseBody['bool'] = [];
        }
        if (!isset($clauseBody['bool']['must'])) {
            $clauseBody['bool']['must'] = [];
        }

        /** @var array<string, mixed> $boolClause */
        $boolClause = $clauseBody['bool'];
        /** @var array<int, mixed> $mustClause */
        $mustClause = $boolClause['must'];

        // Use 'term' for single value, 'terms' for multiple values
        $termQuery = 1 === \count($eventTypes)
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

        $mustClause[] = $termQuery;

        // Update the original structure
        $boolClause['must'] = $mustClause;
        $clauseBody['bool'] = $boolClause;

        return $clauseBody;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDescription(string $resourceClass): array
    {
        $enumValues = array_map(static fn (EventType $type): string => $type->value, EventType::cases());

        return [
            'eventType' => [
                'property' => 'eventType',
                'type' => 'string',
                'required' => false,
                'openapi' => new Parameter(
                    'eventType',
                    'query',
                    'Filter events by event type. Accepts a single value (e.g., ?eventType=commercial_business).',
                    schema: [
                        'type' => 'string',
                        'enum' => $enumValues,
                        'example' => EventType::COMMERCIAL_BUSINESS->value,
                    ],
                ),
            ],
            'eventType[]' => [
                'property' => 'eventType',
                'type' => 'array',
                'required' => false,
                'openapi' => new Parameter(
                    'eventType[]',
                    'query',
                    'Filter events by multiple event types using OR logic - events must match ANY of the specified types (e.g., ?eventType[]=commercial_business&eventType[]=financial returns events with event type "commercial_business" OR "financial").',
                    explode: true,
                    schema: [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'enum' => $enumValues,
                        ],
                        'example' => [EventType::COMMERCIAL_BUSINESS->value, EventType::FINANCIAL->value],
                    ],
                ),
            ],
        ];
    }
}
