<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile\ApiFilter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use Doctrine\ORM\QueryBuilder;

class ActorTypeFilter extends AbstractFilter
{
    /**
     * @param string|array<string>|null $value
     */
    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ('type' !== $property || empty($value)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parameterName = $queryNameGenerator->generateParameterName($property);

        if (\is_array($value)) {
            // Handle multiple types with IN clause
            $queryBuilder
                ->andWhere($queryBuilder->expr()->in("$alias.type", ":$parameterName"))
                ->setParameter($parameterName, $value);
        } else {
            // Handle single type with exact match
            $queryBuilder
                ->andWhere($queryBuilder->expr()->eq("$alias.type", ":$parameterName"))
                ->setParameter($parameterName, $value);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'type' => [
                'property' => 'type',
                'type' => 'string',
                'required' => false,
                'openapi' => new Parameter(
                    'type',
                    'query',
                    'Filter by actor type(s). Can be a single type or multiple types.',
                    schema: [
                        'type' => 'string',
                        'example' => 'company',
                    ],
                ),
            ],
            'type[]' => [
                'property' => 'type',
                'type' => 'array',
                'required' => false,
                'openapi' => new Parameter(
                    'type[]',
                    'query',
                    'Filter by multiple actor types',
                    explode: true,
                    schema: [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                        'example' => ['company', 'competitor'],
                    ],
                ),
            ],
        ];
    }
}
