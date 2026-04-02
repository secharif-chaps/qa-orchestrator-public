<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorStatus;
use App\Domain\Source\DomainMatcher;
use App\Domain\WatchFile\Exception\WatchFileActorNotFoundException;
use App\Domain\WatchFile\WatchFileActor;
use App\Domain\WatchFile\WatchFileActorGatewayInterface;

class NullWatchFileActorGateway implements WatchFileActorGatewayInterface
{
    /**
     * @var array<string, WatchFileActor>
     */
    private array $watchFileActors = [];

    /**
     * @var array<string, array<int, array{type: string, count: int}>>
     */
    private array $actorTypesCounts = [];
    private ?NullWatchFileGateway $watchFileGateway = null;

    public function setWatchFileGateway(NullWatchFileGateway $watchFileGateway): void
    {
        $this->watchFileGateway = $watchFileGateway;
    }

    public function findByActorAndWatchFile(string $actorId, string $watchFileId): WatchFileActor
    {
        foreach ($this->watchFileActors as $watchFileActor) {
            $actor = $watchFileActor->getActor();
            $watchFile = $watchFileActor->getWatchFile();

            if ($actor->getId() === $actorId && $watchFile->getId() === $watchFileId) {
                return $watchFileActor;
            }
        }

        throw new WatchFileActorNotFoundException();
    }

    public function save(WatchFileActor $watchFileActor): void
    {
        $this->watchFileActors[$watchFileActor->getId()] = $watchFileActor;
    }

    /**
     * @return array<int, array{type: string, count: int}>
     */
    public function getActorTypesCounts(string $watchFileId, ?ActorStatus $status = null, ?string $name = null): array
    {
        return $this->actorTypesCounts[$watchFileId] ?? [];
    }

    /**
     * @param array<int, array{type: string, count: int}> $typesCounts
     */
    public function setActorTypesCounts(string $watchFileId, array $typesCounts): void
    {
        $this->actorTypesCounts[$watchFileId] = $typesCounts;
    }

    public function findActorByNormalizedDomain(string $watchFileId, string $normalizedDomain): ?Actor
    {
        $domainMatcher = new DomainMatcher();

        // First, check WatchFileActors saved through the gateway
        foreach ($this->watchFileActors as $watchFileActor) {
            $watchFile = $watchFileActor->getWatchFile();
            if ($watchFile->getId() !== $watchFileId) {
                continue;
            }

            $actor = $watchFileActor->getActor();
            $actorDomain = $actor->getPrimaryDomain();

            if (null === $actorDomain || '' === trim($actorDomain)) {
                continue;
            }

            $normalizedActorDomain = $domainMatcher->normalizeDomain($actorDomain);
            if ($normalizedActorDomain === $normalizedDomain) {
                return $actor;
            }
        }

        // Also check WatchFile's collection if we have access to WatchFileGateway
        // This handles cases where actors are added via $watchFile->addActor() but not saved through gateway
        if (null !== $this->watchFileGateway) {
            try {
                $watchFile = $this->watchFileGateway->get($watchFileId);
                foreach ($watchFile->getWatchFileActors() as $watchFileActor) {
                    $actor = $watchFileActor->getActor();
                    $actorDomain = $actor->getPrimaryDomain();

                    if (null === $actorDomain || '' === trim($actorDomain)) {
                        continue;
                    }

                    $normalizedActorDomain = $domainMatcher->normalizeDomain($actorDomain);
                    if ($normalizedActorDomain === $normalizedDomain) {
                        return $actor;
                    }
                }
            } catch (\App\Domain\WatchFile\Exception\WatchFileNotFoundException) {
                // WatchFile not found, return null
            }
        }

        return null;
    }
}
