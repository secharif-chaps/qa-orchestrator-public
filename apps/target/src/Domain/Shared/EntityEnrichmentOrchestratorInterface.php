<?php

declare(strict_types=1);

namespace App\Domain\Shared;

interface EntityEnrichmentOrchestratorInterface
{
    /**
     * @template T of object
     *
     * @param T                    $entity
     * @param array<string, mixed> $context
     *
     * @return T
     */
    public function enrich(object $entity, array $context = []): object;

    /**
     * @template T of object
     *
     * @param iterable<T>          $entities
     * @param array<string, mixed> $context
     *
     * @return iterable<T>
     */
    public function enrichCollection(iterable $entities, array $context = []): iterable;
}
