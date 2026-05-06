<?php

namespace App\Infrastructure\WatchFile\ApiFilter;

use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Doctrine\Common\Filter\OrderFilterTrait;
use ApiPlatform\Doctrine\Common\Filter\PropertyPlaceholderOpenApiParameterTrait;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\JsonSchemaFilterInterface;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileUser;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class WatchFileOrderFilter extends AbstractFilter implements OrderFilterInterface, JsonSchemaFilterInterface, OpenApiParameterFilterInterface
{
    use OrderFilterTrait {
        OrderFilterTrait::getDescription as private getDescriptionFromApiPlatform;
    }
    use PropertyPlaceholderOpenApiParameterTrait;

    public function __construct(
        private readonly Security $security,
        ?ManagerRegistry $managerRegistry = null,
        ?LoggerInterface $logger = null,
        ?NameConverterInterface $nameConverter = null,
    ) {
        $properties = [
            'name' => [
                'default_direction' => 'asc',
            ],
            'status' => [],
            'countAccess' => [],
            'updatedAt' => [],
        ];

        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);

        $this->orderParameterName = 'sort';
    }

    /**
     * @param array<string, mixed> $context
     */
    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (WatchFile::class !== $resourceClass) {
            return;
        }

        $hasValidSort = false;
        if (
            isset($context['filters'])
            && \is_array($context['filters'])
            && isset($context['filters'][$this->orderParameterName])
            && \is_array($context['filters'][$this->orderParameterName])
            && \count($context['filters'][$this->orderParameterName]) > 0
        ) {
            foreach ($context['filters'][$this->orderParameterName] as $property => $value) {
                $propertyName = $this->denormalizePropertyName($property);

                $direction = $this->normalizeValue($value, $propertyName);
                if (null === $direction) {
                    continue;
                }

                if ('countAccess' === $propertyName) {
                    $hasValidSort = true;
                    $this->applyCountAccessOrder($direction, $queryBuilder);
                    continue;
                }

                if (!$this->isPropertyEnabled($propertyName, $resourceClass)
                    || !$this->isPropertyMapped($propertyName, $resourceClass)) {
                    continue;
                }

                $hasValidSort = true;
                $this->filterProperty(
                    $propertyName,
                    $direction,
                    $queryBuilder,
                    $queryNameGenerator,
                    $resourceClass,
                    $operation,
                    $context
                );
            }
        }

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $this->applyUserFavoritesOrder($queryBuilder, $user);
        }

        if (!$hasValidSort) {
            $this->applyDefaultOrder($queryBuilder, $user instanceof User);
        }
    }

    /**
     * @param string $value
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
        if (
            !$this->isPropertyEnabled($property, $resourceClass)
            || !$this->isPropertyMapped($property, $resourceClass)
        ) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $property;

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty(
                $property,
                $alias,
                $queryBuilder,
                $queryNameGenerator,
                $resourceClass,
                Join::LEFT_JOIN
            );
        }

        if ($this->isPropertyMapped($field, $resourceClass) && 'string' === $this->getDoctrineFieldType(
            $field,
            $resourceClass
        )) {
            if ('status' === $field) {
                $direction = 'desc' === strtolower($value) ? 'DESC' : 'ASC';
                $caseExpr = "CASE
                    WHEN LOWER($alias.status) = 'draft' THEN 1
                    WHEN LOWER($alias.status) = 'archived' THEN 2
                    WHEN LOWER($alias.status) = 'enabled' THEN 3
                    ELSE 4
                END";

                $queryBuilder->addSelect("$caseExpr AS HIDDEN status_order");
                $queryBuilder->resetDQLPart('orderBy');
                $queryBuilder->addOrderBy('status_order', $direction);

                return;
            }

            // Default string field ordering
            $queryBuilder->addSelect(\sprintf(
                '%s AS HIDDEN normalized_%s',
                $this->getNormalizedField($alias, $field),
                $field
            ));

            $queryBuilder->addOrderBy(\sprintf('normalized_%s', $field), $value);

            return;
        }

        // Default non-string field ordering
        $queryBuilder->addOrderBy(\sprintf('%s.%s', $alias, $field), $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchema(Parameter $parameter): array
    {
        return [
            'type' => 'string',
            'enum' => ['asc', 'desc'],
        ];
    }

    public function getDescription(string $resourceClass): array
    {
        $description = $this->getDescriptionFromApiPlatform($resourceClass);

        $properties = $this->getProperties();
        foreach ($properties ?? [] as $property => $propertyOptions) {
            if ($this->isPropertyMapped($property, $resourceClass)) {
                continue;
            }

            $propertyName = $this->normalizePropertyName($property);
            $description[\sprintf('%s[%s]', $this->orderParameterName, $propertyName)] = [
                'property' => $propertyName,
                'type' => 'string',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'default' => strtolower(
                        $propertyOptions['default_direction'] ?? OrderFilterInterface::DIRECTION_ASC
                    ),
                    'enum' => [
                        strtolower(OrderFilterInterface::DIRECTION_ASC),
                        strtolower(OrderFilterInterface::DIRECTION_DESC),
                    ],
                ],
            ];
        }

        return $description;
    }

    private function applyCountAccessOrder(string $direction, QueryBuilder $queryBuilder): void
    {
        $alias = $queryBuilder->getRootAliases()[0];

        $subQuery = $queryBuilder
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(wfu.id)')
            ->from(WatchFileUser::class, 'wfu')
            ->where('wfu.watchFile = ' . $alias . '.id');

        $queryBuilder
            ->addSelect(\sprintf('(%s) AS HIDDEN count_access', $subQuery->getQuery()->getDQL()))
            ->addOrderBy('count_access', $direction);
    }

    private function applyUserFavoritesOrder(QueryBuilder $queryBuilder, User $user): void
    {
        $alias = $queryBuilder->getRootAliases()[0];

        /** @var OrderBy[] $currentOrder */
        $currentOrder = $queryBuilder->getDQLPart('orderBy');

        // Favorite watchfiles first
        $queryBuilder
            ->addSelect('(CASE WHEN uf.id IS NOT NULL THEN 1 ELSE 0 END) AS HIDDEN is_favorite')
            ->leftJoin($alias . '.userFavorites', 'uf', Join::WITH, 'uf.user = :user')
            ->setParameter('user', $user)
            ->orderBy('is_favorite', 'DESC');

        if (\count($currentOrder) > 0) {
            foreach ($currentOrder as $orderBy) {
                $queryBuilder->addOrderBy($orderBy);
            }
        }
    }

    private function applyDefaultOrder(QueryBuilder $queryBuilder, bool $isLoggedUser): void
    {
        $alias = $queryBuilder->getRootAliases()[0];

        /** @var OrderBy[]|null $currentOrder */
        $currentOrder = $queryBuilder->getDQLPart('orderBy');

        $minimalCountOrderToApplyDefault = $isLoggedUser ? 1 : 0;
        if (null !== $currentOrder && \count($currentOrder) > $minimalCountOrderToApplyDefault) {
            return;
        }

        $queryBuilder
            ->addSelect(\sprintf('%s AS HIDDEN normalized_name', $this->getNormalizedField($alias, 'name')))
            ->addOrderBy('normalized_name', 'ASC')
            ->addOrderBy($alias . '.id', 'ASC');
    }

    private function getNormalizedField(string $alias, string $field): string
    {
        return \sprintf('LOWER(UNACCENT(%s.%s))', $alias, $field);
    }
}
