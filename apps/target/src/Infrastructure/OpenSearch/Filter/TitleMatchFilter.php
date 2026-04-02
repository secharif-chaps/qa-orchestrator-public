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
use App\Domain\WatchFileEvent\WatchFileEvent;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Custom filter for searching in title.fr and title.en fields.
 * This filter properly handles nested object fields in OpenSearch.
 */
class TitleMatchFilter extends AbstractFilter implements FilterInterface, ConstantScoreFilterInterface
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
        if (WatchFileEvent::class !== $resourceClass) {
            return $clauseBody;
        }

        $filters = $context['filters'] ?? [];

        if (!\is_array($filters)) {
            return $clauseBody;
        }

        /** @var array<string, mixed> $filters */
        $titleFr = $filters['title.fr']
            ?? $filters['title[fr]']
            ?? $filters['title_fr']
            ?? null;
        $titleEn = $filters['title.en']
            ?? $filters['title[en]']
            ?? $filters['title_en']
            ?? null;

        if (\is_array($titleFr)) {
            $titleFr = !empty($titleFr) ? reset($titleFr) : null;
        }
        if (\is_array($titleEn)) {
            $titleEn = !empty($titleEn) ? reset($titleEn) : null;
        }

        $titleFrStr = \is_string($titleFr) && '' !== trim($titleFr) ? trim($titleFr) : null;
        $titleEnStr = \is_string($titleEn) && '' !== trim($titleEn) ? trim($titleEn) : null;

        if (null === $titleFrStr && null === $titleEnStr) {
            return $clauseBody;
        }

        // Initialize bool clause if needed
        if (!isset($clauseBody['bool'])) {
            $clauseBody['bool'] = [];
        }
        if (!\is_array($clauseBody['bool'])) {
            $clauseBody['bool'] = [];
        }
        if (!isset($clauseBody['bool']['must'])) {
            $clauseBody['bool']['must'] = [];
        }
        if (!\is_array($clauseBody['bool']['must'])) {
            $clauseBody['bool']['must'] = [];
        }

        /** @var array<string, mixed> $boolClause */
        $boolClause = $clauseBody['bool'];
        /** @var array<int, mixed> $mustClause */
        $mustClause = $boolClause['must'];

        if (null !== $titleFrStr) {
            $mustClause[] = [
                'match' => [
                    'title.fr' => [
                        'query' => $titleFrStr,
                    ],
                ],
            ];
        }

        if (null !== $titleEnStr) {
            $mustClause[] = [
                'match' => [
                    'title.en' => [
                        'query' => $titleEnStr,
                    ],
                ],
            ];
        }

        // Update the bool clause
        $clauseBody['bool']['must'] = $mustClause;

        return $clauseBody;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $description['title.fr'] = [
            'property' => 'title.fr',
            'type' => 'string',
            'required' => false,
            'description' => 'Search in French title',
        ];

        $description['title.en'] = [
            'property' => 'title.en',
            'type' => 'string',
            'required' => false,
            'description' => 'Search in English title',
        ];

        return $description;
    }
}
