<?php

declare(strict_types=1);

namespace App\Infrastructure\Source;

use App\Domain\Actor\Actor;
use App\Domain\Source\CollectStatus;
use App\Domain\Source\DomainMatcher;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceNotFoundException;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\UsageLimit\ResourceCount;
use App\Domain\WatchFile\WatchFile;
use App\UserInterface\Dto\Source\SourceGroup;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SourceDoctrineGateway implements SourceGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly DomainMatcher $domainMatcher,
        private readonly SourceDuplicateCache $duplicateCache,
    ) {
    }

    public function save(Source $source): void
    {
        $this->entityManager->persist($source);
        $this->entityManager->flush();

        // Add to cache after successful save
        $this->duplicateCache->addToCache($source->getWatchFile(), $source);
    }

    public function alreadyExist(WatchFile $watchFile, Source $source): bool
    {
        // FAST PATH 0: Check Valkey cache first (O(1) lookup, ~2ms)
        $cachedDuplicate = $this->duplicateCache->isDuplicate($watchFile, $source);
        if ($cachedDuplicate) {
            return true; // Found in cache
        }

        // FAST PATH 1: Check for exact URL match (indexed DB query)
        $exactMatch = $this->entityManager
            ->getRepository(Source::class)
            ->createQueryBuilder('s')
            ->andWhere('s.watchFile = :watchfile')
            ->setParameter('watchfile', $watchFile)
            ->andWhere('s.url = :url')
            ->setParameter('url', $source->getUrl())
            ->andWhere('s.type = :type')
            ->setParameter('type', $source->getType())
            ->getQuery()
            ->getOneOrNullResult();

        if (null !== $exactMatch) {
            // Warm up cache for future lookups
            $existingSources = $this->entityManager
                ->getRepository(Source::class)
                ->createQueryBuilder('s')
                ->andWhere('s.watchFile = :watchfile')
                ->setParameter('watchfile', $watchFile)
                ->andWhere('s.type = :type')
                ->setParameter('type', $source->getType())
                ->getQuery()
                ->getResult();
            $this->duplicateCache->warmUp($watchFile, $source->getType(), $existingSources);

            return true;
        }

        // Strategy 2: Check for primaryDomain + query match (semantic duplicates)
        $newPrimaryDomain = $source->getPrimaryDomain();
        $newQuery = $source->getQuery();

        if (null !== $newQuery && '' !== trim($newQuery) && '' !== trim($newPrimaryDomain)) {
            $normalizedNewDomain = $this->domainMatcher->normalizeDomain($newPrimaryDomain);

            if (null !== $normalizedNewDomain) {
                $domainBasedSources = $this->entityManager
                    ->getRepository(Source::class)
                    ->createQueryBuilder('s')
                    ->andWhere('s.watchFile = :watchfile')
                    ->setParameter('watchfile', $watchFile)
                    ->andWhere('s.type = :type')
                    ->setParameter('type', $source->getType())
                    ->andWhere('s.query IS NOT NULL')
                    ->andWhere('s.primaryDomain IS NOT NULL')
                    ->getQuery()
                    ->getResult();

                foreach ($domainBasedSources as $existingSource) {
                    $existingDomain = $existingSource->getPrimaryDomain();
                    $existingQuery = $existingSource->getQuery();

                    if (null === $existingQuery) {
                        continue;
                    }

                    $normalizedExistingDomain = $this->domainMatcher->normalizeDomain($existingDomain);

                    // Check if domains match AND queries match (case-insensitive, trimmed)
                    if (
                        null !== $normalizedExistingDomain
                        && $normalizedExistingDomain === $normalizedNewDomain
                        && strtolower(trim($existingQuery)) === strtolower(trim($newQuery))
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function countSourcesForWatchFile(WatchFile $watchFile): ResourceCount
    {
        $count = (int) $this->entityManager
            ->getRepository(Source::class)
            ->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.watchFile = :watchfile')
            ->setParameter('watchfile', $watchFile)
            ->getQuery()
            ->getSingleScalarResult();

        return ResourceCount::fromInt($count);
    }

    public function get(string $id): Source
    {
        $source = $this->entityManager->getRepository(Source::class)->find($id);

        if (!$source instanceof Source) {
            throw new SourceNotFoundException('Source not found for id ' . $id);
        }

        return $source;
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $sources = $this->entityManager->getRepository(Source::class)
            ->createQueryBuilder('s')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $sourcesById = [];
        foreach ($sources as $source) {
            $sourcesById[$source->getId()] = $source;
        }

        return $sourcesById;
    }

    public function updateSourcesStatus(array $sources, SourceStatus $status): void
    {
        if (empty($sources)) {
            return;
        }

        $sourceIds = array_map(fn (Source $source) => $source->getId(), $sources);

        $qb = $this->entityManager
            ->createQueryBuilder()
            ->update(Source::class, 's')
            ->set('s.status', ':status')
            ->where('s.id IN (:ids)')
            ->setParameter('status', $status)
            ->setParameter('ids', $sourceIds)
            ->getQuery()
            ->execute();

        // Update the source objects in memory with the new status
        foreach ($sources as $source) {
            $source->setStatus($status);
        }
    }

    public function findByActor(Actor|string $actor): array
    {
        return $this->entityManager->getRepository(Source::class)->findBy([
            'actor' => $actor,
        ]);
    }

    public function findByActorAndWatchFile(Actor|string $actor, WatchFile $watchFile): array
    {
        return $this->entityManager->getRepository(Source::class)->findBy([
            'actor' => $actor,
            'watchFile' => $watchFile,
        ]);
    }

    public function countSourcesByActorForWatchFile(WatchFile|string $watchFile): array
    {
        $watchFileId = $watchFile instanceof WatchFile ? $watchFile->getId() : $watchFile;

        $results = $this->entityManager->getRepository(Source::class)
            ->createQueryBuilder('s')
            ->select('IDENTITY(s.actor) as actorId, COUNT(s.id) as sourceCount')
            ->where('s.watchFile = :watchFileId')
            ->andWhere('s.actor IS NOT NULL')
            ->setParameter('watchFileId', $watchFileId)
            ->groupBy('s.actor')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        /** @var array{actorId: string, sourceCount: int|string} $row */
        foreach ($results as $row) {
            $counts[$row['actorId']] = (int) $row['sourceCount'];
        }

        return $counts;
    }

    public function sourcesGrouped(WatchFile|string $watchFile, ?string $searchQuery = null): array
    {
        $watchFileId = $watchFile instanceof WatchFile ? $watchFile->getId() : $watchFile;

        $sourceGroups = [];

        // First query: Fetch sources with error status (only active sources)
        $errorQb = $this->entityManager->getRepository(Source::class)->createQueryBuilder('s');
        $errorQb
            ->where('s.watchFile = :watchFileId')
            ->andWhere('s.collectStatus = :errorStatus')
            ->andWhere('s.status = :activeStatus')
            ->andWhere('s.type != :manualType')
            ->setParameter('watchFileId', $watchFileId)
            ->setParameter('errorStatus', CollectStatus::ERROR)
            ->setParameter('activeStatus', SourceStatus::ACTIVE)
            ->setParameter('manualType', SourceType::MANUAL);

        if ($searchQuery) {
            $this->applySearchFilter($errorQb, $searchQuery);
        }

        $errorSources = $errorQb
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Create error group if there are error sources
        if (!empty($errorSources)) {
            $sourceGroups[] = new SourceGroup(
                type: 'error',
                typeLabel: 'Error Sources',
                count: \count($errorSources),
                sources: new ArrayCollection($errorSources)
            );
        }

        // Second query: Fetch normal sources (excluding error sources, only active sources)
        $normalQb = $this->entityManager->getRepository(Source::class)->createQueryBuilder('s');
        $normalQb
            ->where('s.watchFile = :watchFileId')
            ->andWhere('s.collectStatus != :errorStatus')
            ->andWhere('s.status = :activeStatus')
            ->andWhere('s.type != :manualType')
            ->setParameter('watchFileId', $watchFileId)
            ->setParameter('errorStatus', CollectStatus::ERROR)
            ->setParameter('activeStatus', SourceStatus::ACTIVE)
            ->setParameter('manualType', SourceType::MANUAL);

        if ($searchQuery) {
            $this->applySearchFilter($normalQb, $searchQuery);
        }

        $normalSources = $normalQb
            ->orderBy('s.type', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Group normal sources by type
        $groupedSources = [];
        foreach ($normalSources as $source) {
            $type = $source->getType()
->value;
            if (!isset($groupedSources[$type])) {
                $groupedSources[$type] = [];
            }
            $groupedSources[$type][] = $source;
        }

        // Create SourceGroup objects for normal sources
        foreach ($groupedSources as $type => $sourcesOfType) {
            $sourceGroups[] = new SourceGroup(
                type: $type,
                typeLabel: $this->getTypeLabel(SourceType::from($type)),
                count: \count($sourcesOfType),
                sources: new ArrayCollection($sourcesOfType)
            );
        }

        return $sourceGroups;
    }

    private function applySearchFilter(\Doctrine\ORM\QueryBuilder $queryBuilder, string $searchQuery): void
    {
        $escapedQuery = addcslashes($searchQuery, '%_\\');
        $queryBuilder->andWhere('(LOWER(s.name) LIKE LOWER(:search) OR LOWER(s.primaryDomain) LIKE LOWER(:search))')
            ->setParameter('search', '%' . $escapedQuery . '%');
    }

    private function getTypeLabel(SourceType $type): string
    {
        $translationKey = 'source_type.' . strtolower($type->name);

        return $this->translator->trans($translationKey, [], 'messages');
    }

    public function getPrimaryDomains(array $sourceIds = []): array
    {
        $result = $this->entityManager->getRepository(Source::class)->createQueryBuilder('s')
            ->select('s.primaryDomain, s.id')
            ->where('s.id IN (:sourceIds)')
            ->setParameter('sourceIds', $sourceIds)
            ->getQuery()
            ->getArrayResult();

        return array_combine(array_column($result, 'id'), array_column($result, 'primaryDomain'));
    }

    public function countActiveByWatchFile(WatchFile $watchFile): ResourceCount
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $count = $queryBuilder->select('COUNT(s.id)')
            ->from(Source::class, 's')
            ->where('s.watchFile = :watchFile')
            ->andWhere('s.status = :sourceStatus')
            ->setParameter('watchFile', $watchFile)
            ->setParameter('sourceStatus', SourceStatus::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        return ResourceCount::fromInt((int) $count);
    }

    /**
     * @return array<string, int> Array of source type values with their counts
     */
    public function countSourceTypesByWatchFile(
        string $watchFileId,
        ?SourceStatus $status = null,
        ?string $name = null,
    ): array {
        $queryBuilder = $this->entityManager->getRepository(Source::class)
            ->createQueryBuilder('s')
            ->select('s.type, COUNT(s.id) as count')
            ->where('s.watchFile = :watchFileId')
            ->andWhere('s.type != :manualType')
            ->setParameter('watchFileId', $watchFileId)
            ->setParameter('manualType', SourceType::MANUAL)
            ->groupBy('s.type');

        if (null !== $status) {
            if (SourceStatus::INACTIVE === $status) {
                // Both INACTIVE and AUTO_DISABLED are considered inactive
                $queryBuilder
                    ->andWhere('s.status IN (:statuses)')
                    ->setParameter('statuses', [SourceStatus::INACTIVE, SourceStatus::AUTO_DISABLED]);
            } else {
                $queryBuilder
                    ->andWhere('s.status = :status')
                    ->setParameter('status', $status);
            }
        }

        if (null !== $name && '' !== $name) {
            $this->applySearchFilter($queryBuilder, $name);
        }

        $results = $queryBuilder
            ->getQuery()
            ->getArrayResult();

        $typeCounts = [];
        foreach ($results as $row) {
            if (!\is_array($row) || !isset($row['type']) || !isset($row['count'])) {
                throw new \RuntimeException('Invalid result row structure');
            }
            $type = $row['type'];
            $typeValue = $type instanceof SourceType ? $type->value : (\is_string(
                $type
            ) ? $type : throw new \RuntimeException('Invalid type value in result'));
            $typeCounts[$typeValue] = (int) $row['count'];
        }

        return $typeCounts;
    }

    /**
     * Find orphaned sources (sources without actor) for a watchfile that match a given domain.
     */
    public function findOrphanedSourcesByDomain(WatchFile|string $watchFile, string $domain): array
    {
        $watchFileId = $watchFile instanceof WatchFile ? $watchFile->getId() : $watchFile;

        // Normalize domain: remove www prefix if present for comparison
        $normalizedDomain = $this->normalizeDomain($domain);

        // Build query to find orphaned sources matching the domain
        $queryBuilder = $this->entityManager->getRepository(Source::class)
            ->createQueryBuilder('s')
            ->where('s.watchFile = :watchFileId')
            ->andWhere('s.actor IS NULL')
            ->setParameter('watchFileId', $watchFileId);

        // Match domain with www variations
        // Match both "domain.com" and "www.domain.com" in source's primaryDomain
        $domainWithWww = 'www.' . $normalizedDomain;
        $queryBuilder->andWhere(
            '(LOWER(s.primaryDomain) = LOWER(:domain) OR LOWER(s.primaryDomain) = LOWER(:domainWithWww))'
        )
            ->setParameter('domain', $normalizedDomain)
            ->setParameter('domainWithWww', $domainWithWww);

        return $queryBuilder->getQuery()
->getResult();
    }

    /**
     * Normalize a domain by removing www prefix if present.
     * This ensures consistent domain comparison.
     */
    private function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        $lowerDomain = strtolower($domain);

        // Remove www. prefix if present
        if (str_starts_with($lowerDomain, 'www.')) {
            return substr($domain, 4);
        }

        return $domain;
    }
}
