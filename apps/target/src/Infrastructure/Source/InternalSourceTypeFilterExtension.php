<?php

declare(strict_types=1);

namespace App\Infrastructure\Source;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use Doctrine\ORM\QueryBuilder;

/**
 * Exclude internal source types (manual, etc.) from all Source API collection queries.
 * This runs at the SQL level before pagination, so totalItems counts are correct.
 */
readonly class InternalSourceTypeFilterExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (Source::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $paramName = $queryNameGenerator->generateParameterName('internalType');

        $queryBuilder
            ->andWhere(\sprintf('%s.type != :%s', $rootAlias, $paramName))
            ->setParameter($paramName, SourceType::MANUAL);
    }
}
