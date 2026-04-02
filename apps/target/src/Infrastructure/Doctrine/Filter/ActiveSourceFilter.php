<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Domain\Source\SourceStatus;
use Doctrine\ORM\QueryBuilder;

class ActiveSourceFilter extends AbstractFilter
{
    private const string FILTER_NAME = 'active';

    /**
     * @param bool|int|float|string $value
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
        if (self::FILTER_NAME !== $property) {
            return;
        }

        // Convert string to boolean
        $isActive = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        if (null === $isActive) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $parameterName = $queryNameGenerator->generateParameterName('status');

        if ($isActive) {
            // Only ACTIVE status is considered active
            $queryBuilder
                ->andWhere(\sprintf('%s.status = :%s', $rootAlias, $parameterName))
                ->setParameter($parameterName, SourceStatus::ACTIVE);
        } else {
            // Both INACTIVE and AUTO_DISABLED are considered inactive
            $queryBuilder
                ->andWhere(\sprintf('%s.status != :%s', $rootAlias, $parameterName))
                ->setParameter($parameterName, SourceStatus::ACTIVE);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::FILTER_NAME => [
                'property' => self::FILTER_NAME,
                'type' => 'bool',
                'required' => false,
                'description' => 'Filter sources by active status (true for active sources, false for inactive/auto-disabled sources)',
                'openapi' => new Parameter(
                    self::FILTER_NAME,
                    'query',
                    'Filter sources by active status (true for active sources, false for inactive/auto-disabled sources)',
                    schema: [
                        'type' => 'boolean',
                    ]
                ),
            ],
        ];
    }
}
