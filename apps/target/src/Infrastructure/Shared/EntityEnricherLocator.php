<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared;

use App\Domain\Shared\EntityEnricherInterface;
use App\Domain\Shared\EntityEnricherLocatorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class EntityEnricherLocator implements EntityEnricherLocatorInterface
{
    /**
     * @var array<class-string, array<EntityEnricherInterface<object>>>
     */
    private array $enrichersByClass = [];

    /**
     * @var array<class-string, array<EntityEnricherInterface<object>>>
     */
    private array $enrichersCache = [];

    /**
     * @var array<string, bool>
     */
    private array $inheritanceCache = [];

    /**
     * @param iterable<EntityEnricherInterface<object>> $enrichers
     */
    public function __construct(#[AutowireIterator('app.entity_enricher')] iterable $enrichers)
    {
        foreach ($enrichers as $enricher) {
            $supportedClass = $enricher->supports();
            $this->enrichersByClass[$supportedClass][] = $enricher;
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $entityClass
     *
     * @return array<EntityEnricherInterface<T>>
     */
    public function getEnrichersFor(string $entityClass): array
    {
        if (isset($this->enrichersCache[$entityClass])) {
            /** @var array<EntityEnricherInterface<T>> */
            return $this->enrichersCache[$entityClass];
        }

        $enrichers = [];

        if (isset($this->enrichersByClass[$entityClass])) {
            $enrichers = array_merge($enrichers, $this->enrichersByClass[$entityClass]);
        }

        foreach ($this->enrichersByClass as $supportedClass => $classEnrichers) {
            if ($supportedClass === $entityClass) {
                continue;
            }

            if ($this->isSubclassOf($entityClass, $supportedClass)) {
                $enrichers = array_merge($enrichers, $classEnrichers);
            }
        }

        $this->enrichersCache[$entityClass] = $enrichers;

        /** @var array<EntityEnricherInterface<T>> */
        return $enrichers;
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return array<EntityEnricherInterface<T>>
     */
    public function getEnrichersForEntity(object $entity): array
    {
        $entityClass = $entity::class;

        return $this->getEnrichersFor($entityClass);
    }

    private function isSubclassOf(string $childClass, string $parentClass): bool
    {
        $cacheKey = "{$childClass}::{$parentClass}";

        if (isset($this->inheritanceCache[$cacheKey])) {
            return $this->inheritanceCache[$cacheKey];
        }

        $result = is_subclass_of($childClass, $parentClass);
        $this->inheritanceCache[$cacheKey] = $result;

        return $result;
    }
}
