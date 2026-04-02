<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile\ApiFilter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\Source\Source;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Filter that filters Source collections by actor and watchFile
 * when these values are provided in the context filters.
 * This filter is used by ActorSourceProvider to filter sources
 * for a specific actor within a watch file.
 */
class ActorSourceFilter extends AbstractFilter
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
     * @param array<string, mixed> $context
     */
    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (Source::class !== $resourceClass) {
            return;
        }

        // Only apply if both actor.id and watchFile.id are in context filters
        if (!isset($context['filters']) || !\is_array($context['filters'])) {
            return;
        }

        $actorId = $context['filters']['actor.id'] ?? null;
        $watchFileId = $context['filters']['watchFile.id'] ?? null;

        if (!\is_string($actorId) || !\is_string($watchFileId)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $actorParam = $queryNameGenerator->generateParameterName('actor');
        $watchFileParam = $queryNameGenerator->generateParameterName('watchFile');

        $queryBuilder
            ->andWhere(\sprintf('%s.actor = :%s', $alias, $actorParam))
            ->andWhere(\sprintf('%s.watchFile = :%s', $alias, $watchFileParam))
            ->setParameter($actorParam, $actorId)
            ->setParameter($watchFileParam, $watchFileId);
    }

    /**
     * @param string               $value
     * @param array<string, mixed> $context
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
        throw new \LogicException(
            'This method should not be called as this is a global filter only meant to be applied once'
        );
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}
