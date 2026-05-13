<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\DocumentQuality;

use App\Domain\DocumentQuality\AdblockListCategory;
use App\Infrastructure\DocumentQuality\CachedAdblockDomainListProvider;
use App\Tests\Units\Infrastructure\DocumentQuality\Stub\InMemoryAdblockListSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(CachedAdblockDomainListProvider::class)]
class CachedAdblockDomainListProviderTest extends TestCase
{
    public function testFindMatchReturnsNullWhenDomainIsClean(): void
    {
        $source = new InMemoryAdblockListSource('stevenblack')
            ->withEntry('doubleclick.net', AdblockListCategory::ADS);

        $provider = new CachedAdblockDomainListProvider(sources: [$source], cache: new ArrayAdapter());

        self::assertNull($provider->findMatch('lemonde.fr'));
    }

    public function testFindMatchReturnsMatchForFlaggedDomain(): void
    {
        $source = new InMemoryAdblockListSource('stevenblack')
            ->withEntry('doubleclick.net', AdblockListCategory::TRACKING);

        $provider = new CachedAdblockDomainListProvider(sources: [$source], cache: new ArrayAdapter());

        $match = $provider->findMatch('doubleclick.net');

        self::assertNotNull($match);
        self::assertSame('doubleclick.net', $match->domain);
        self::assertSame(AdblockListCategory::TRACKING, $match->category);
        self::assertSame('stevenblack', $match->sourceName);
    }

    public function testFindMatchNormalizesWwwAndSubdomains(): void
    {
        $source = new InMemoryAdblockListSource('stevenblack')
            ->withEntry('doubleclick.net', AdblockListCategory::TRACKING);

        $provider = new CachedAdblockDomainListProvider(sources: [$source], cache: new ArrayAdapter());

        self::assertNotNull($provider->findMatch('www.doubleclick.net'));
        self::assertNotNull($provider->findMatch('ads.doubleclick.net'));
    }

    public function testFirstWriteWinsAcrossSources(): void
    {
        $first = new InMemoryAdblockListSource('stevenblack')
            ->withEntry('shared.example', AdblockListCategory::MALWARE);
        $second = new InMemoryAdblockListSource('easylist')
            ->withEntry('shared.example', AdblockListCategory::ADS);

        $provider = new CachedAdblockDomainListProvider(sources: [$first, $second], cache: new ArrayAdapter());

        $match = $provider->findMatch('shared.example');

        self::assertNotNull($match);
        self::assertSame('stevenblack', $match->sourceName);
        self::assertSame(AdblockListCategory::MALWARE, $match->category);
    }

    public function testSourceFailureIsToleratedAndOtherSourcesStillFeedConsolidatedSet(): void
    {
        $failing = new InMemoryAdblockListSource('failing')
->failOnFetch();
        $working = new InMemoryAdblockListSource('easylist')
            ->withEntry('doubleclick.net', AdblockListCategory::ADS);

        $provider = new CachedAdblockDomainListProvider(sources: [$failing, $working], cache: new ArrayAdapter());

        self::assertNotNull($provider->findMatch('doubleclick.net'));
    }

    public function testAllSourcesFailingProducesNoMatches(): void
    {
        $sourceA = new InMemoryAdblockListSource('a')
->failOnFetch();
        $sourceB = new InMemoryAdblockListSource('b')
->failOnFetch();

        $provider = new CachedAdblockDomainListProvider(sources: [$sourceA, $sourceB], cache: new ArrayAdapter());

        self::assertNull($provider->findMatch('anything.com'));
    }

    public function testRefreshReturnsConsolidatedEntryCount(): void
    {
        $source = new InMemoryAdblockListSource('stevenblack')
            ->withEntry('one.example')
            ->withEntry('two.example')
            ->withEntry('three.example');

        $provider = new CachedAdblockDomainListProvider(sources: [$source], cache: new ArrayAdapter());

        self::assertSame(3, $provider->refresh());
    }

    public function testRefreshDeduplicatesAcrossSources(): void
    {
        $a = new InMemoryAdblockListSource('a')
            ->withEntry('dup.example')
            ->withEntry('only-a.example');
        $b = new InMemoryAdblockListSource('b')
            ->withEntry('dup.example')
            ->withEntry('only-b.example');

        $provider = new CachedAdblockDomainListProvider(sources: [$a, $b], cache: new ArrayAdapter());

        self::assertSame(3, $provider->refresh());
    }

    public function testCacheHitAvoidsResourceRefetchOnSecondCall(): void
    {
        $source = new class implements \App\Domain\DocumentQuality\AdblockListSourceInterface {
            public int $fetchCount = 0;

            public function name(): string
            {
                return 'counting';
            }

            public function fetchEntries(): iterable
            {
                ++$this->fetchCount;
                yield new \App\Domain\DocumentQuality\AdblockListEntry('flagged.example', AdblockListCategory::ADS);
            }
        };

        $cache = new ArrayAdapter();
        $providerA = new CachedAdblockDomainListProvider(sources: [$source], cache: $cache);
        $providerA->findMatch('flagged.example');

        $providerB = new CachedAdblockDomainListProvider(sources: [$source], cache: $cache);
        $providerB->findMatch('flagged.example');

        self::assertSame(1, $source->fetchCount, 'second provider should hit the shared cache');
    }

    public function testRefreshOverwritesCache(): void
    {
        $stale = new InMemoryAdblockListSource('stale')
            ->withEntry('old.example');

        $cache = new ArrayAdapter();
        $providerStale = new CachedAdblockDomainListProvider(sources: [$stale], cache: $cache);
        $providerStale->findMatch('old.example'); // primes the cache

        $fresh = new InMemoryAdblockListSource('fresh')
            ->withEntry('new.example');
        $providerFresh = new CachedAdblockDomainListProvider(sources: [$fresh], cache: $cache);
        $providerFresh->refresh();

        self::assertNotNull($providerFresh->findMatch('new.example'));
    }
}
