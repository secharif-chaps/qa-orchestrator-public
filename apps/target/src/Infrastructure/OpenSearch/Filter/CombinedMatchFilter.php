<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenSearch\Filter;

use ApiPlatform\Elasticsearch\Filter\AbstractFilter;
use ApiPlatform\Elasticsearch\Filter\ConstantScoreFilterInterface;
use ApiPlatform\Elasticsearch\Filter\FilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class CombinedMatchFilter extends AbstractFilter implements FilterInterface, ConstantScoreFilterInterface
{
    /**
     * @param array<string, mixed>|null $properties
     */
    public function __construct(
        PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        PropertyMetadataFactoryInterface $propertyMetadataFactory,
        ResourceClassResolverInterface $resourceClassResolver,
        ?NameConverterInterface $nameConverter = null,
        ?array $properties = null,
        private string $fieldName = 'search',
    ) {
        parent::__construct($propertyNameCollectionFactory, $propertyMetadataFactory, $resourceClassResolver, $nameConverter, $properties);
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
        $filters = $context['filters'] ?? [];
        $fieldName = $this->fieldName;

        if (!\is_array($filters) || !isset($filters[$fieldName]) || empty($filters[$fieldName])) {
            return $clauseBody;
        }

        $searchValue = $filters[$fieldName];
        $searchFields = array_keys($this->properties ?? []);

        if (empty($searchFields)) {
            return $clauseBody;
        }

        // Create a multi_match query across all specified properties
        if (!isset($clauseBody['bool'])) {
            $clauseBody['bool'] = [];
        }
        if (\is_array($clauseBody['bool']) && !isset($clauseBody['bool']['must'])) {
            $clauseBody['bool']['must'] = [];
        }

        // Type assertion to help PHPStan understand the structure
        \assert(\is_array($clauseBody['bool']));
        \assert(\is_array($clauseBody['bool']['must']));

        /** @var array<string, mixed> $boolClause */
        $boolClause = $clauseBody['bool'];
        /** @var array<int, mixed> $mustClause */
        $mustClause = $boolClause['must'];

        $mustClause[] = [
            'multi_match' => [
                'query' => $searchValue,
                'fields' => $searchFields,
                'type' => 'best_fields',
                'fuzziness' => 'AUTO',
            ],
        ];

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
        $description = [];
        $fieldName = $this->fieldName;

        $description[$fieldName] = [
            'property' => $fieldName,
            'type' => 'string',
            'required' => false,
            'description' => 'Search across multiple fields: ' . implode(', ', array_keys($this->properties ?? [])),
        ];

        return $description;
    }
}
