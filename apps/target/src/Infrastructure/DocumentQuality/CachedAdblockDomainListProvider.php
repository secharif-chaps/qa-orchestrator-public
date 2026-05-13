<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentQuality;

use App\Domain\DocumentQuality\AdblockDomainListProviderInterface;
use App\Domain\DocumentQuality\AdblockListCategory;
use App\Domain\DocumentQuality\AdblockListSourceInterface;
use App\Domain\DocumentQuality\AdblockMatch;
use App\Domain\DocumentQuality\Exception\AdblockSourceUnavailableException;
use App\Domain\Shared\DomainNormalizer;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Consolidates multiple adblock list sources into a single in-memory HashSet
 * backed by Symfony Cache (Valkey/Redis) for cross-instance reuse.
 *
 * Strategy:
 *  - Lazy load on the first `findMatch()` call.
 *  - Stored payload format: `array<string, string>` where the value is
 *    `"<sourceName>:<categoryValue>"` — compact enough to keep millions of
 *    entries in a single cache item (~5MB serialized for ~200k domains).
 *  - First-write-wins when a domain is present in multiple sources, which
 *    preserves attribution to the most authoritative source (sources are
 *    iterated in injection order).
 *  - Source failures are partial: surviving sources still feed the
 *    consolidated set. When every source fails the set stays empty and
 *    `findMatch()` returns null (graceful degradation — the processor
 *    will emit a neutral signal rather than a misleading "flagged").
 */
#[AsAlias(AdblockDomainListProviderInterface::class)]
class CachedAdblockDomainListProvider implements AdblockDomainListProviderInterface
{
    public const string CACHE_KEY = 'adblock_domain_list.consolidated.v1';
    private const int CACHE_TTL_SECONDS = 86_400;

    /** @var array<string, string>|null Lazy-loaded HashSet of `domain => "source:category"` */
    private ?array $domains = null;

    /**
     * @param iterable<AdblockListSourceInterface> $sources
     */
    public function __construct(
        #[AutowireIterator('app.adblock_list_source')]
        private readonly iterable $sources,
        private readonly CacheInterface $cache,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function findMatch(string $domain): ?AdblockMatch
    {
        $normalized = DomainNormalizer::root($domain);
        if (null === $normalized) {
            return null;
        }

        $domains = $this->loadDomains();
        if (!isset($domains[$normalized])) {
            return null;
        }

        return $this->decode($normalized, $domains[$normalized]);
    }

    public function refresh(): int
    {
        $consolidated = $this->consolidate();
        $this->cache->delete(self::CACHE_KEY);
        $this->cache->get(self::CACHE_KEY, static function (CacheItemInterface $item) use ($consolidated): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            return $consolidated;
        });

        $this->domains = $consolidated;

        return \count($consolidated);
    }

    /**
     * @return array<string, string>
     */
    private function loadDomains(): array
    {
        if (null !== $this->domains) {
            return $this->domains;
        }

        try {
            /** @var array<string, string> $loaded */
            $loaded = $this->cache->get(self::CACHE_KEY, function (CacheItemInterface $item): array {
                $item->expiresAfter(self::CACHE_TTL_SECONDS);

                return $this->consolidate();
            });
            $this->domains = $loaded;
        } catch (\Throwable $exception) {
            $this->logger?->warning('Adblock consolidated list could not be loaded', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->domains = [];
        }

        return $this->domains;
    }

    /**
     * @return array<string, string>
     */
    private function consolidate(): array
    {
        $consolidated = [];
        $loadedSources = 0;

        foreach ($this->sources as $source) {
            $sourceName = $source->name();

            try {
                foreach ($source->fetchEntries() as $entry) {
                    if (!isset($consolidated[$entry->domain])) {
                        $consolidated[$entry->domain] = $sourceName . ':' . $entry->category->value;
                    }
                }
                ++$loadedSources;
            } catch (AdblockSourceUnavailableException $exception) {
                $this->logger?->warning('Adblock source skipped during consolidation', [
                    'source' => $sourceName,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->logger?->info('Adblock domain list consolidated', [
            'sources_loaded' => $loadedSources,
            'unique_domains' => \count($consolidated),
        ]);

        return $consolidated;
    }

    private function decode(string $domain, string $payload): AdblockMatch
    {
        $parts = explode(':', $payload, 2);
        $sourceName = '' !== $parts[0] ? $parts[0] : 'unknown';
        $category = AdblockListCategory::tryFrom($parts[1] ?? '') ?? AdblockListCategory::UNCLASSIFIED;

        return new AdblockMatch(domain: $domain, category: $category, sourceName: $sourceName);
    }
}
