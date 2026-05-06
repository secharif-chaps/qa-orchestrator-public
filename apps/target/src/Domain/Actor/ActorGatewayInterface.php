<?php

declare(strict_types=1);

namespace App\Domain\Actor;

interface ActorGatewayInterface
{
    public function getByLabel(string $label): Actor;

    /**
     * @param array<int, string> $labels
     *
     * @return array<string, Actor>
     */
    public function getByLabels(array $labels): array;

    public function get(string $id): Actor;

    /**
     * @param string[] $ids
     *
     * @return array<string, Actor> Actors indexed by their ID
     */
    public function findByIds(array $ids): array;

    public function save(Actor $actor): void;

    public function countByWatchFileId(string $watchFileId): int;

    /**
     * Find an actor by its primary domain.
     *
     * @return Actor|null The actor if found, null otherwise
     */
    public function findByPrimaryDomain(string $primaryDomain): ?Actor;

    /**
     * Get an actor by ID and WatchFile ID, only if the actor is linked to the WatchFile.
     *
     * @param string $actorId     The actor ID
     * @param string $watchFileId The watchfile ID
     *
     * @return Actor|null The actor if found and linked to the WatchFile, null otherwise
     */
    public function getByWatchFileAndId(string $actorId, string $watchFileId): ?Actor;
}
