<?php

declare(strict_types=1);

namespace App\Infrastructure\Source;

use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

readonly class SourceDuplicateCache
{
    public function __construct(
        private CacheInterface $cache,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Check if a URL already exists in the cache for this WatchFile.
     *
     * @return bool True if duplicate found in cache, false if not found or cache miss
     */
    public function isDuplicate(WatchFile $watchFile, Source $source): bool
    {
        $url = $source->getUrl();
        if ('' === $url) {
            return false;
        }

        $cacheKey = $this->getCacheKey($watchFile->getId(), $source->getType());

        try {
            // Get the set of URLs from cache
            /** @var string[] $cachedUrls */
            $cachedUrls = $this->cache->get($cacheKey, fn () => []);

            // Check if URL exists in the set (exact match)
            return \in_array($url, $cachedUrls, true);
        } catch (\Exception $e) {
            $this->logger?->warning('Cache check failed, falling back to database', [
                'watch_file_id' => $watchFile->getId(),
                'source_type' => $source->getType()
->value,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Add a URL to the cache.
     */
    public function addToCache(WatchFile $watchFile, Source $source): void
    {
        $url = $source->getUrl();
        if ('' === $url) {
            return;
        }

        $cacheKey = $this->getCacheKey($watchFile->getId(), $source->getType());

        try {
            // Get existing URLs
            /** @var string[] $cachedUrls */
            $cachedUrls = $this->cache->get($cacheKey, fn () => []);

            // Add new URL to the set if not already present
            if (!\in_array($url, $cachedUrls, true)) {
                $cachedUrls[] = $url;

                // Update cache with new set
                $this->cache->delete($cacheKey);
                $this->cache->get($cacheKey, fn () => $cachedUrls);
            }

            $this->logger?->debug('Added URL to duplicate cache', [
                'watch_file_id' => $watchFile->getId(),
                'url' => $url,
            ]);
        } catch (\Exception $e) {
            $this->logger?->warning('Failed to add URL to cache', [
                'watch_file_id' => $watchFile->getId(),
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Invalidate the cache for a specific WatchFile and source type.
     */
    public function invalidate(string $watchFileId, SourceType $type): void
    {
        $cacheKey = $this->getCacheKey($watchFileId, $type);
        $this->cache->delete($cacheKey);

        $this->logger?->debug('Invalidated source duplicate cache', [
            'watch_file_id' => $watchFileId,
            'source_type' => $type->value,
        ]);
    }

    /**
     * Invalidate all source caches for a WatchFile.
     */
    public function invalidateAll(string $watchFileId): void
    {
        foreach (SourceType::cases() as $type) {
            $this->invalidate($watchFileId, $type);
        }

        $this->logger?->debug('Invalidated all source duplicate caches', [
            'watch_file_id' => $watchFileId,
        ]);
    }

    /**
     * Warm up the cache by loading all sources.
     *
     * @param Source[] $sources
     */
    public function warmUp(WatchFile $watchFile, SourceType $type, array $sources): void
    {
        $urls = [];

        foreach ($sources as $source) {
            if ($source->getType() !== $type) {
                continue;
            }

            $url = $source->getUrl();
            if ('' !== $url) {
                $urls[] = $url;
            }
        }

        $cacheKey = $this->getCacheKey($watchFile->getId(), $type);
        $this->cache->delete($cacheKey);
        $this->cache->get($cacheKey, fn () => $urls);

        $this->logger?->debug('Warmed up source duplicate cache', [
            'watch_file_id' => $watchFile->getId(),
            'source_type' => $type->value,
            'count' => \count($urls),
        ]);
    }

    private function getCacheKey(string $watchFileId, SourceType $type): string
    {
        // Replace reserved characters ({}()/\@:-) with underscores for Symfony cache key compatibility
        $sanitizedId = str_replace(['-', ':'], '_', $watchFileId);

        return \sprintf('watchfile_%s_source_urls_%s', $sanitizedId, $type->value);
    }
}
