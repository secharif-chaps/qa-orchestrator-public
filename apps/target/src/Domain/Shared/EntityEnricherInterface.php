<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for enriching entities with additional computed/virtual properties.
 *
 * @template T of object
 */
#[AutoconfigureTag('app.entity_enricher')]
interface EntityEnricherInterface
{
    /**
     * @param T                    $entity
     * @param array<string, mixed> $context Additional context for enrichment
     *
     * @return T The enriched entity
     */
    public function enrich(object $entity, array $context = []): object;

    /**
     * @param iterable<T>          $entities
     * @param array<string, mixed> $context
     *
     * @return iterable<T>
     */
    public function enrichCollection(iterable $entities, array $context = []): iterable;

    /**
     * @return class-string<T>
     */
    public function supports(): string;
}
