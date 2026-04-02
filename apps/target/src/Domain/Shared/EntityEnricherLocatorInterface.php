<?php

declare(strict_types=1);

namespace App\Domain\Shared;

interface EntityEnricherLocatorInterface
{
    /**
     * @template T of object
     *
     * @param class-string<T> $entityClass
     *
     * @return array<EntityEnricherInterface<T>>
     */
    public function getEnrichersFor(string $entityClass): array;

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return array<EntityEnricherInterface<T>>
     */
    public function getEnrichersForEntity(object $entity): array;
}
