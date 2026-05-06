<?php

declare(strict_types=1);

namespace App\Domain\Source;

use App\Domain\Actor\Actor;
use App\Domain\UsageLimit\ResourceCount;
use App\Domain\WatchFile\WatchFile;
use App\UserInterface\Dto\Source\SourceGroup;

interface SourceGatewayInterface
{
    public function get(string $id): Source;

    /**
     * @param string[] $ids
     *
     * @return array<string, Source> Sources indexed by their ID
     */
    public function findByIds(array $ids): array;

    public function alreadyExist(WatchFile $watchFile, Source $source): bool;

    public function countSourcesForWatchFile(WatchFile $watchFile): ResourceCount;

    public function save(Source $source): void;

    /**
     * @return Source[]
     */
    public function findByActor(Actor|string $actor): array;

    /**
     * @return Source[]
     */
    public function findByActorAndWatchFile(Actor|string $actor, WatchFile $watchFile): array;

    /**
     * Count sources grouped by actor for a specific watchfile.
     * Returns an array where keys are actor IDs and values are source counts.
     *
     * @return array<string, int>
     */
    public function countSourcesByActorForWatchFile(WatchFile|string $watchFile): array;

    /**
     * @param Source[] $sources
     */
    public function updateSourcesStatus(array $sources, SourceStatus $status): void;

    /**
     * @return SourceGroup[]
     */
    public function sourcesGrouped(WatchFile|string $watchFile, ?string $searchQuery = null): array;

    /**
     * @param string[] $sourceIds
     *
     * @return array<string, string>
     */
    public function getPrimaryDomains(array $sourceIds = []): array;

    /**
     * Count the number of active Sources for a given watchfile.
     *
     * @param WatchFile $watchFile The watchfile to count active sources for
     */
    public function countActiveByWatchFile(WatchFile $watchFile): ResourceCount;

    /**
     * Count sources by type for a watchfile, optionally filtered by status and/or name.
     *
     * @param string            $watchFileId The watchfile ID
     * @param SourceStatus|null $status      Optional status filter
     * @param string|null       $name        Optional name filter (case-insensitive partial match on name or primary domain)
     *
     * @return array<string, int> Array of source type values with their counts
     */
    public function countSourceTypesByWatchFile(
        string $watchFileId,
        ?SourceStatus $status = null,
        ?string $name = null,
    ): array;

    /**
     * Find orphaned sources (sources without actor) for a watchfile that match a given domain.
     *
     * @param WatchFile|string $watchFile The watchfile or its ID
     * @param string           $domain    The domain to match (without www prefix)
     *
     * @return Source[] Array of matching orphaned sources
     */
    public function findOrphanedSourcesByDomain(WatchFile|string $watchFile, string $domain): array;
}
