<?php

namespace App\Domain\WatchFile;

use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorStatus;

interface WatchFileActorGatewayInterface
{
    public function findByActorAndWatchFile(string $actorId, string $watchFileId): WatchFileActor;

    /**
     * Get actor types with their counts for a watchfile.
     *
     * @param string           $watchFileId The watchfile ID
     * @param ActorStatus|null $status      Optional status filter
     * @param string|null      $name        Optional name filter (case-insensitive partial match on actor label)
     *
     * @return array<int, array{type: string, count: int}>
     */
    public function getActorTypesCounts(string $watchFileId, ?ActorStatus $status = null, ?string $name = null): array;

    /**
     * Find an actor by normalized domain within a watchfile.
     *
     * This method performs domain matching with normalization (case-insensitive, www prefix handling).
     * The normalizedDomain parameter should already be normalized (lowercase, www prefix stripped).
     *
     * @param string $watchFileId      The watchfile ID
     * @param string $normalizedDomain The normalized domain to search for (e.g., "example.com")
     *
     * @return Actor|null The matching actor if found, null otherwise
     */
    public function findActorByNormalizedDomain(string $watchFileId, string $normalizedDomain): ?Actor;

    public function save(WatchFileActor $watchFileActor): void;
}
