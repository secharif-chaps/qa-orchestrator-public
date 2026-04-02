<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile\ApiFilter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Shared\NormalizeBoolTrait;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Filter that allows filtering WatchFile entities to show only favorites.
 *
 * Usage: ?onlyFavorites=true|false
 *
 * When onlyFavorites=true, only returns WatchFiles that are marked as favorites by the current user.
 * When onlyFavorites=false or not provided, returns all accessible WatchFiles.
 */
class OnlyFavoritesFilter extends AbstractFilter
{
    use NormalizeBoolTrait;

    /**
     * @param array<string, mixed>|null $properties
     */
    public function __construct(
        private readonly Security $security,
        ?ManagerRegistry $managerRegistry = null,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
    ) {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }

    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ('onlyFavorites' !== $property) {
            return;
        }

        if (WatchFile::class !== $resourceClass) {
            return;
        }

        $loggedUser = $this->security->getUser();
        if (!$loggedUser instanceof User) {
            return; // No logged user, no filtering
        }

        // Normalize the value to boolean
        $onlyFavorites = $this->normalizeBooleanValue($value);
        if (null === $onlyFavorites) {
            return; // Invalid value, ignore filter
        }

        $alias = $queryBuilder->getRootAliases()[0];

        if ($onlyFavorites) {
            // Check if the userFavorites join already exists
            $favoritesAlias = $this->getOrCreateUserFavoritesJoin($queryBuilder, $alias, $loggedUser);

            // Filter to show only favorites
            $queryBuilder->andWhere(\sprintf('%s.id IS NOT NULL', $favoritesAlias));
        }
        // If onlyFavorites is false, we don't add any additional filtering
        // The existing UserAccessibleWatchFileFilter will handle access control
    }

    public function getDescription(string $resourceClass): array
    {
        if (WatchFile::class !== $resourceClass) {
            return [];
        }

        return [
            'onlyFavorites' => [
                'property' => 'onlyFavorites',
                'type' => 'boolean',
                'required' => false,
                'description' => 'Filter to show only favorite WatchFiles for the current user',
                'schema' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ];
    }

    private function findExistingUserFavoritesJoin(QueryBuilder $queryBuilder, string $rootAlias): ?string
    {
        $joins = $queryBuilder->getDQLPart('join');
        if (!\is_array($joins) || !isset($joins[$rootAlias]) || !is_iterable($joins[$rootAlias])) {
            return null;
        }

        $rootJoins = $joins[$rootAlias];

        foreach ($rootJoins as $join) {
            if (!$join instanceof Join) {
                continue;
            }

            if ($join->getJoin() === $rootAlias . '.userFavorites') {
                // Extract the alias from the join
                $joinCondition = $join->getCondition();
                $joinAlias = $join->getAlias();
                if ($joinCondition && \is_string($joinCondition) && str_contains(
                    $joinCondition,
                    $joinAlias . '.user'
                )) {
                    return $joinAlias;
                }
            }
        }

        return null;
    }

    private function getOrCreateUserFavoritesJoin(QueryBuilder $queryBuilder, string $rootAlias, User $user): string
    {
        $existingAlias = $this->findExistingUserFavoritesJoin($queryBuilder, $rootAlias);
        if (null !== $existingAlias) {
            return $existingAlias;
        }

        // Create new join
        $queryBuilder
            ->innerJoin(\sprintf('%s.userFavorites', $rootAlias), 'uf')
            ->andWhere('uf.user = :favorite_user')
            ->setParameter('favorite_user', $user);

        return 'uf';
    }
}
