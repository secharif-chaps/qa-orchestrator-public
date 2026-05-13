<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\DocumentQuality\Stub;

use App\Domain\DocumentQuality\AdblockDomainListProviderInterface;
use App\Domain\DocumentQuality\AdblockListCategory;
use App\Domain\DocumentQuality\AdblockMatch;
use App\Domain\Shared\DomainNormalizer;

/**
 * Hand-rolled in-memory provider for tests. Honors apps/target's convention
 * of preferring `Null*` / `InMemory*` doubles over PHPUnit's createStub
 * (cf. memory feedback_target_use_null_test_doubles).
 *
 * @see AdblockDomainListProviderInterface
 */
class InMemoryAdblockDomainListProvider implements AdblockDomainListProviderInterface
{
    /** @var array<string, AdblockMatch> */
    private array $matches = [];
    private bool $shouldFail = false;
    public int $refreshCallCount = 0;

    public function addMatch(
        string $domain,
        AdblockListCategory $category = AdblockListCategory::ADS,
        string $sourceName = 'in_memory',
    ): self {
        $normalized = DomainNormalizer::root($domain) ?? $domain;
        $this->matches[$normalized] = new AdblockMatch(
            domain: $normalized,
            category: $category,
            sourceName: $sourceName,
        );

        return $this;
    }

    public function failOnFindMatch(): self
    {
        $this->shouldFail = true;

        return $this;
    }

    public function findMatch(string $domain): ?AdblockMatch
    {
        if ($this->shouldFail) {
            throw new \RuntimeException('Adblock provider unavailable (simulated)');
        }

        $normalized = DomainNormalizer::root($domain) ?? $domain;

        return $this->matches[$normalized] ?? null;
    }

    public function refresh(): int
    {
        ++$this->refreshCallCount;

        return \count($this->matches);
    }
}
