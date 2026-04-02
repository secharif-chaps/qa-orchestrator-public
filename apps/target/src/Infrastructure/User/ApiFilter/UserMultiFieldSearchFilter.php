<?php

declare(strict_types=1);

namespace App\Infrastructure\User\ApiFilter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use Doctrine\ORM\QueryBuilder;

use function Symfony\Component\String\u;

class UserMultiFieldSearchFilter extends AbstractFilter
{
    /**
     * @param bool|string|int|float|null $value
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
        if ('search' !== $property || empty($value)) {
            return;
        }

        if (!\is_string($value)) {
            throw new \InvalidArgumentException('The search value must be a string.');
        }

        $alias = $queryBuilder->getRootAliases()[0];

        $exp = $queryBuilder->expr();
        $orX = $exp->orX(
            $exp->like("LOWER($alias.email)", ':search'),
            $exp->like("LOWER($alias.firstName)", ':search'),
            $exp->like("LOWER($alias.lastName)", ':search'),
            $exp->like("LOWER($alias.userName)", ':search'),
        );

        $queryBuilder
            ->andWhere($orX)
            ->setParameter('search', '%' . u($value)->lower() . '%');
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'search' => [
                'type' => 'string',
                'required' => false,
                'openapi' => new Parameter(
                    'search',
                    'query',
                    'Search in email, firstName, lastName or username',
                    schema: [
                        'type' => 'string',
                        'example' => 'john',
                    ],
                ),
            ],
        ];
    }
}
