<?php

declare(strict_types=1);

namespace App\Infrastructure\User\ApiFilter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Uid\Uuid;

class ExcludeWatchFileSharedUsersFilter extends AbstractFilter
{
    /**
     * @param array<string, mixed>|null $properties
     */
    public function __construct(
        ?ManagerRegistry $managerRegistry = null,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
    ) {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }

    /**
     * @param string|int|null $value
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
        if ('excludeWatchFileSharedUsers' !== $property) {
            return;
        }

        if (!\is_string($value) || !Uuid::isValid($value)) {
            throw new \InvalidArgumentException('The value for "excludeWatchFileSharedUsers" must be a valid UUID.');
        }

        $alias = $queryBuilder->getRootAliases()[0];

        $queryBuilder
            ->leftJoin("$alias.watchFileUsers", 'fu', 'WITH', 'fu.watchFile = :excludeWatchFile')
            ->andWhere('fu.watchFile IS NULL')
            ->setParameter('excludeWatchFile', $value);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'excludeWatchFileSharedUsers' => [
                'type' => 'string',
                'required' => false,
                'openapi' => new Parameter(
                    'excludeWatchFileSharedUsers',
                    'query',
                    'Exclude users who have access to the watch file from the results.',
                    schema: [
                        'type' => 'string',
                        'format' => 'uuid',
                    ],
                    example: '550e8400-e29b-41d4-a716-446655440000',
                ),
            ],
        ];
    }
}
