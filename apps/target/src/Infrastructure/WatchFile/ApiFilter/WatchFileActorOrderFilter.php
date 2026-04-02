<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile\ApiFilter;

use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Doctrine\Common\Filter\OrderFilterTrait;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\WatchFile\WatchFileActor;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Custom order filter for WatchFileActor that applies alphabetical sorting by actor label
 * with French collation as the default behavior.
 */
class WatchFileActorOrderFilter extends AbstractFilter implements OrderFilterInterface
{
    use OrderFilterTrait;

    public function __construct(
        ?ManagerRegistry $managerRegistry = null,
        ?LoggerInterface $logger = null,
        ?NameConverterInterface $nameConverter = null,
    ) {
        $properties = [
            'score' => [],
            'type' => [],
            'actor.label' => [
                'default_direction' => 'asc',
            ],
        ];

        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);

        $this->orderParameterName = 'order';
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
        if (WatchFileActor::class !== $resourceClass) {
            return;
        }

        $sortParams = [];
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

                if (!$this->isPropertyEnabled($propertyName, $resourceClass)
                    || !$this->isPropertyMapped($propertyName, $resourceClass)) {
                    continue;
                }

                $sortParams[$propertyName] = $direction;
            }
        }

        // Reset orderBy to ensure actor.label is always first
        $queryBuilder->resetDQLPart('orderBy');

        $alias = $queryBuilder->getRootAliases()[0];

        // ensure actors are always returned in alphabetical order as per requirements
        $actorLabelDirection = $sortParams['actor.label'] ?? 'ASC';
        $this->applyActorLabelOrder($queryBuilder, $alias, $actorLabelDirection);

        // apply other sorts as secondary sorts
        foreach ($sortParams as $propertyName => $direction) {
            if ('actor.label' === $propertyName) {
                continue;
            }

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
        $alias = $queryBuilder->getRootAliases()[0];

        // Handle actor.label with proper join and French collation
        if ('actor.label' === $property) {
            $this->applyActorLabelOrder($queryBuilder, $alias, $value);

            return;
        }

        // Handle other properties
        if ('score' === $property || 'type' === $property) {
            $queryBuilder->addOrderBy(\sprintf('%s.%s', $alias, $property), $value);

            return;
        }

        // Default handling for other properties
        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty(
                $property,
                $alias,
                $queryBuilder,
                $queryNameGenerator,
                $resourceClass,
                Join::LEFT_JOIN
            );
        } else {
            $field = $property;
        }

        $queryBuilder->addOrderBy(\sprintf('%s.%s', $alias, $field), $value);
    }

    /**
     * Apply alphabetical ordering by actor label with French collation.
     */
    private function applyActorLabelOrder(QueryBuilder $queryBuilder, string $alias, string $direction): void
    {
        // Ensure actor join exists
        $joinAlias = 'a';
        $hasActorJoin = false;
        /** @var array<string, array<int, Join>>|null $joinParts */
        $joinParts = $queryBuilder->getDQLPart('join');
        if (null !== $joinParts) {
            foreach ($joinParts as $joins) {
                /** @var array<int, Join> $joins */
                foreach ($joins as $join) {
                    if ($join->getAlias() === $joinAlias) {
                        $hasActorJoin = true;
                        break 2;
                    }
                }
            }
        }

        if (!$hasActorJoin) {
            $queryBuilder->leftJoin(\sprintf('%s.actor', $alias), $joinAlias);
        }

        $queryBuilder->addSelect(\sprintf(
            'LOWER(UNACCENT(%s.label)) AS HIDDEN normalized_actor_label',
            $joinAlias
        ));
        $queryBuilder->addOrderBy('normalized_actor_label', $direction);
    }
}
