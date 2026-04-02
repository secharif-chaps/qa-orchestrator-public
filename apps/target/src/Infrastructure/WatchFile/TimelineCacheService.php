<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\WatchFile\Timeline;
use App\Domain\WatchFile\WatchFile;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Cache service for WatchFile Timeline to improve performance.
 * Uses Redis to cache timeline data for frequently accessed WatchFiles.
 */
class TimelineCacheService
{
    private const CACHE_PREFIX = 'timeline';
    private const DEFAULT_TTL = 900; // 15 minutes

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly int $cacheTtl = self::DEFAULT_TTL,
    ) {
    }

    /**
     * Get cached timeline data for a WatchFile.
     */
    public function getTimeline(WatchFile $watchFile, int $page, int $limit): ?Timeline
    {
        $cacheKey = $this->generateCacheKey($watchFile->getId(), $page, $limit);

        try {
            $cacheItem = $this->cache->getItem($cacheKey);

            if ($cacheItem->isHit()) {
                $this->logger->debug('Timeline cache hit', [
                    'watch_file_id' => $watchFile->getId(),
                    'page' => $page,
                    'limit' => $limit,
                    'cache_key' => $cacheKey,
                ]);

                $cachedData = $cacheItem->get();
                if ($this->isValidCachedData($cachedData)) {
                    /** @var array<string, mixed> $cachedData */
                    return $this->deserializeTimeline($cachedData);
                }
            }

            $this->logger->debug('Timeline cache miss', [
                'watch_file_id' => $watchFile->getId(),
                'page' => $page,
                'limit' => $limit,
                'cache_key' => $cacheKey,
            ]);

            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get timeline from cache', [
                'watch_file_id' => $watchFile->getId(),
                'page' => $page,
                'limit' => $limit,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Cache timeline data for a WatchFile.
     */
    public function setTimeline(WatchFile $watchFile, int $page, int $limit, Timeline $timeline): void
    {
        $cacheKey = $this->generateCacheKey($watchFile->getId(), $page, $limit);

        try {
            $cacheItem = $this->cache->getItem($cacheKey);
            $serializedData = $this->serializeTimeline($timeline);

            $cacheItem->set($serializedData);
            $cacheItem->expiresAfter($this->cacheTtl);

            $this->cache->save($cacheItem);

            $this->logger->debug('Timeline cached successfully', [
                'watch_file_id' => $watchFile->getId(),
                'page' => $page,
                'limit' => $limit,
                'cache_key' => $cacheKey,
                'ttl' => $this->cacheTtl,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to cache timeline', [
                'watch_file_id' => $watchFile->getId(),
                'page' => $page,
                'limit' => $limit,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Invalidate all cached timeline data for a WatchFile.
     * Called when new activities are created for the WatchFile.
     */
    public function invalidateTimeline(WatchFile $watchFile): void
    {
        try {
            // Clear all timeline pages for this WatchFile
            // We use a pattern to clear all pages at once
            $pattern = $this->generateCacheKeyPattern($watchFile->getId());

            // Note: Symfony Cache doesn't have native pattern deletion,
            // so we clear individual pages that we know about
            $this->clearKnownPages($watchFile->getId());

            $this->logger->info('Timeline cache invalidated', [
                'watch_file_id' => $watchFile->getId(),
                'pattern' => $pattern,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to invalidate timeline cache', [
                'watch_file_id' => $watchFile->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate cache key for timeline data.
     */
    private function generateCacheKey(?string $watchFileId, int $page, int $limit): string
    {
        return \sprintf('%s.%s.p%d.l%d', self::CACHE_PREFIX, $watchFileId, $page, $limit);
    }

    /**
     * Generate cache key pattern for all pages of a WatchFile.
     */
    private function generateCacheKeyPattern(?string $watchFileId): string
    {
        return \sprintf('%s.%s.*', self::CACHE_PREFIX, $watchFileId);
    }

    /**
     * Clear known pages for a WatchFile (first few pages that are most accessed).
     */
    private function clearKnownPages(?string $watchFileId): void
    {
        $commonPageSizes = [30, 50, 100]; // Common page sizes
        $pagesToClear = [1, 2, 3, 4, 5]; // First pages are most accessed

        $keysToDelete = [];

        foreach ($commonPageSizes as $limit) {
            foreach ($pagesToClear as $page) {
                $keysToDelete[] = $this->generateCacheKey($watchFileId, $page, $limit);
            }
        }

        $this->cache->deleteItems($keysToDelete);
    }

    /**
     * Serialize Timeline object to array for caching.
     *
     * @return array<string, mixed>
     */
    private function serializeTimeline(Timeline $timeline): array
    {
        return [
            'events' => $timeline->getEvents(),
            'totalItems' => $timeline->getTotalItems(),
            'currentPage' => $timeline->getCurrentPage(),
            'itemsPerPage' => $timeline->getItemsPerPage(),
            'totalPages' => $timeline->getTotalPages(),
            'cached_at' => time(),
        ];
    }

    /**
     * Deserialize cached data back to Timeline object.
     *
     * @param array<string, mixed> $data
     */
    private function deserializeTimeline(array $data): Timeline
    {
        $events = $data['events'] ?? [];
        $totalItems = $data['totalItems'] ?? 0;
        $currentPage = $data['currentPage'] ?? 1;
        $itemsPerPage = $data['itemsPerPage'] ?? 30;
        $totalPages = $data['totalPages'] ?? 1;

        // Validate and cast events to expected format
        $validatedEvents = [];
        if (\is_array($events)) {
            foreach ($events as $event) {
                if (\is_array($event)) {
                    $validatedEvents[] = $event;
                }
            }
        }

        return new Timeline(
            events: $validatedEvents,
            totalItems: \is_int($totalItems) ? $totalItems : 0,
            currentPage: \is_int($currentPage) ? $currentPage : 1,
            itemsPerPage: \is_int($itemsPerPage) ? $itemsPerPage : 30,
            totalPages: \is_int($totalPages) ? $totalPages : 1
        );
    }

    /**
     * Validate cached data structure.
     */
    private function isValidCachedData(mixed $data): bool
    {
        return \is_array($data)
            && isset($data['events'])
            && isset($data['totalItems'])
            && isset($data['currentPage'])
            && isset($data['itemsPerPage'])
            && isset($data['totalPages'])
            && isset($data['cached_at']);
    }
}
