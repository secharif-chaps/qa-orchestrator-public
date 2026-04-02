<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenSearch\Filter;

use ApiPlatform\Elasticsearch\Filter\AbstractFilter;
use ApiPlatform\Elasticsearch\Filter\ConstantScoreFilterInterface;
use ApiPlatform\Elasticsearch\Filter\FilterInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\Document\Document;

class DocumentValidationStatusFilter extends AbstractFilter implements FilterInterface, ConstantScoreFilterInterface
{
    public const string AI_PREFIX = 'ai_';
    public const string MANUAL_PREFIX = 'manual_';
    public const string EMPTY_SUFFIX = 'empty';

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
        // Only apply to Document resources
        if (Document::class !== $resourceClass) {
            return $clauseBody;
        }

        $filters = $context['filters'] ?? [];

        if (!\is_array($filters) || !isset($filters['validationStatus'])) {
            return $clauseBody;
        }

        $validationStatuses = $filters['validationStatus'];

        // Ensure it's an array
        if (!\is_array($validationStatuses)) {
            $validationStatuses = [$validationStatuses];
        }

        if (empty($validationStatuses)) {
            return $clauseBody;
        }

        // Initialize bool structure
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

        // Build should clauses for OR logic - at least one condition must be satisfied
        $shouldClauses = [];

        foreach ($validationStatuses as $status) {
            if (!\is_string($status)) {
                continue;
            }

            // Handle AI validation statuses
            if (str_starts_with($status, self::AI_PREFIX)) {
                if ($status === self::AI_PREFIX . self::EMPTY_SUFFIX) {
                    // Filter for documents where aiValidation is null
                    $shouldClauses[] = [
                        'bool' => [
                            'must_not' => [
                                'exists' => [
                                    'field' => 'aiValidation',
                                ],
                            ],
                        ],
                    ];
                } else {
                    // Remove AI prefix to get the actual status value
                    $aiStatusValue = substr($status, \strlen(self::AI_PREFIX));
                    $shouldClauses[] = [
                        'term' => [
                            'aiValidation.status' => $aiStatusValue,
                        ],
                    ];
                }
            }

            // Handle manual validation statuses
            if (str_starts_with($status, self::MANUAL_PREFIX)) {
                if ($status === self::MANUAL_PREFIX . self::EMPTY_SUFFIX) {
                    // Filter for documents where manualStatus is null
                    $shouldClauses[] = [
                        'bool' => [
                            'must_not' => [
                                'exists' => [
                                    'field' => 'manualStatus',
                                ],
                            ],
                        ],
                    ];
                } else {
                    // Remove MANUAL prefix to get the actual status value
                    $manualStatusValue = substr($status, \strlen(self::MANUAL_PREFIX));
                    $shouldClauses[] = [
                        'term' => [
                            'manualStatus' => $manualStatusValue,
                        ],
                    ];
                }
            }
        }

        // Add should clauses with minimum_should_match to implement OR logic
        if (!empty($shouldClauses)) {
            $mustClause[] = [
                'bool' => [
                    'should' => $shouldClauses,
                    'minimum_should_match' => 1,
                ],
            ];
        }

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
        return [
            'validationStatus' => [
                'property' => 'validationStatus',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter by validation status. Accepts a single value (e.g., ?validationStatus=manual_empty)',
            ],
            'validationStatus[]' => [
                'property' => 'validationStatus',
                'type' => 'array',
                'required' => false,
                'description' => 'Filter by multiple validation statuses using OR logic - documents must match ANY of the specified statuses (e.g., ?validationStatus[]=ai_empty&validationStatus[]=manual_accept returns documents with no AI validation OR manually accepted)',
            ],
        ];
    }
}
