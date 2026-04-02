<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\Shared\EntityEnricherInterface;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\WatchFile\WatchFileActor;
use Webmozart\Assert\Assert;

/**
 * Enriches WatchFileActor entities with their sources count.
 * Performs a single batch query to get counts for all actors in a watchfile.
 *
 * @implements EntityEnricherInterface<WatchFileActor>
 */
class WatchFileActorSourcesCountEnricher implements EntityEnricherInterface
{
    public function __construct(
        private readonly SourceGatewayInterface $sourceGateway,
    ) {
    }

    public function enrich(object $entity, array $context = []): object
    {
        $watchFileId = $context['watchFileId'] ?? $entity->getWatchFile()->getId();
        Assert::string($watchFileId);
        Assert::uuid($watchFileId);

        $countsByActorId = $this->sourceGateway->countSourcesByActorForWatchFile($watchFileId);

        $actorId = $entity->getActor()
->getId();
        $count = $countsByActorId[$actorId] ?? 0;
        $entity->setSourcesCount($count);

        return $entity;
    }

    public function enrichCollection(iterable $entities, array $context = []): iterable
    {
        $watchFileId = $context['watchFileId'] ?? null;

        if (null === $watchFileId) {
            return $entities;
        }
        Assert::string($watchFileId);
        Assert::uuid($watchFileId);

        // Fetch all source counts in one query
        $countsByActorId = $this->sourceGateway->countSourcesByActorForWatchFile($watchFileId);

        // Iterate over the original iterable and set counts
        foreach ($entities as $watchFileActor) {
            $actorId = $watchFileActor->getActor()
->getId();
            $count = $countsByActorId[$actorId] ?? 0;
            $watchFileActor->setSourcesCount($count);
        }

        return $entities;
    }

    public function supports(): string
    {
        return WatchFileActor::class;
    }
}
