<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared;

use App\Domain\Shared\EntityEnricherLocatorInterface;
use App\Domain\Shared\EntityEnrichmentOrchestratorInterface;
use Psr\Log\LoggerInterface;

class EntityEnrichmentOrchestrator implements EntityEnrichmentOrchestratorInterface
{
    public function __construct(
        private readonly EntityEnricherLocatorInterface $enricherLocator,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function enrich(object $entity, array $context = []): object
    {
        $enrichers = $this->enricherLocator->getEnrichersForEntity($entity);

        if (empty($enrichers)) {
            return $entity;
        }

        $this->logger?->debug('Applying enrichers to entity', [
            'entity_class' => $entity::class,
            'enrichers_count' => \count($enrichers),
        ]);

        foreach ($enrichers as $enricher) {
            try {
                $entity = $enricher->enrich($entity, $context);
            } catch (\Throwable $e) {
                $this->logger?->error('Error applying enricher', [
                    'enricher_class' => $enricher::class,
                    'entity_class' => $entity::class,
                    'error' => $e->getMessage(),
                ]);

                // Continue with other enrichers
            }
        }

        return $entity;
    }

    public function enrichCollection(iterable $entities, array $context = []): iterable
    {
        $entitiesByClass = [];

        // Group entities by class for optimization
        foreach ($entities as $index => $entity) {
            if (!\is_scalar($index)) {
                continue;
            }

            $entitiesByClass[$entity::class][(string) $index] = $entity;
        }

        // Apply enrichers per class
        foreach ($entitiesByClass as $class => $classEntities) {
            $enrichers = $this->enricherLocator->getEnrichersFor($class);

            if (empty($enrichers)) {
                continue;
            }

            foreach ($enrichers as $enricher) {
                try {
                    $enricher->enrichCollection($classEntities, $context);
                } catch (\Throwable $e) {
                    $this->logger?->error('Error applying enricher to collection', [
                        'enricher_class' => $enricher::class,
                        'entity_class' => $class,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // reset pointer of original iterator
        if ($entities instanceof \Iterator) {
            $entities->rewind();
        }

        return $entities;
    }
}
