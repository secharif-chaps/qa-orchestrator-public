<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect\Collector;

use App\Application\Collect\Collector\GetCollectorListAction;
use App\Application\Collect\Collector\GetCollectorListHandler;
use App\Domain\Collect\ValueObject\Collector;
use App\Domain\Source\SourceType;
use App\Tests\Units\Infrastructure\Collect\NullProviderGateway;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class GetCollectorListHandlerTest extends TestCase
{
    private GetCollectorListHandler $handler;
    private ArrayAdapter $cache;
    private NullProviderGateway $providerGateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new ArrayAdapter();
        $this->providerGateway = new NullProviderGateway();

        $this->handler = new GetCollectorListHandler($this->cache, $this->providerGateway);
    }

    public function testInvokeReturnsCollectorsFromGatewayOnCacheMiss(): void
    {
        $collectors = [
            new Collector(
                name: 'collector-1',
                displayName: [
                    'en' => 'Collector 1',
                ],
                description: [
                    'en' => 'Desc 1',
                ],
                type: 'test',
                version: '1.0',
                iconUrl: 'url/1',
                parameters: [],
                returnTypes: [],
                supportStream: true,
                supportBatch: false,
                supportedSourceTypes: [SourceType::WEBSITE],
            ),
            new Collector(
                name: 'collector-2',
                displayName: [
                    'en' => 'Collector 2',
                ],
                description: [
                    'en' => 'Desc 2',
                ],
                type: 'test',
                version: '1.0',
                iconUrl: 'url/2',
                parameters: [],
                returnTypes: [],
                supportStream: false,
                supportBatch: true,
                supportedSourceTypes: [],
            ),
        ];

        $this->providerGateway->setCollectors($collectors);

        // First call (cache miss)
        $result1 = ($this->handler)(new GetCollectorListAction());

        self::assertEquals($collectors, $result1);

        // Second call (cache hit) - should return same data without calling gateway again
        $result2 = ($this->handler)(new GetCollectorListAction());

        self::assertEquals($collectors, $result2, 'Should return the same list from cache');
    }

    public function testInvokeReturnsCollectorsFromCacheOnHit(): void
    {
        $collectors = [
            new Collector(
                name: 'collector-1',
                displayName: [
                    'en' => 'Collector 1',
                ],
                description: [
                    'en' => 'Desc 1',
                ],
                type: 'test',
                version: '1.0',
                iconUrl: 'url/1',
                parameters: [],
                returnTypes: [],
                supportStream: true,
                supportBatch: false,
                supportedSourceTypes: [SourceType::WEBSITE],
            ),
        ];

        $this->providerGateway->setCollectors($collectors);

        // Prime the cache
        ($this->handler)(new GetCollectorListAction());

        // Change gateway collectors to verify cache is being used
        $this->providerGateway->setCollectors([]);

        // Should still return original collectors from cache
        $result = ($this->handler)(new GetCollectorListAction());

        self::assertCount(1, $result);
        self::assertSame('collector-1', $result[0]->name);
    }

    public function testInvokeReturnsEmptyListWhenNoCollectors(): void
    {
        $this->providerGateway->setCollectors([]);

        $result = ($this->handler)(new GetCollectorListAction());
        self::assertEmpty($result);
    }

    public function testInvokePropagatesExceptionFromGateway(): void
    {
        $exception = new \RuntimeException('Provider service unavailable');
        $this->providerGateway->throwException($exception);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provider service unavailable');

        ($this->handler)(new GetCollectorListAction());
    }

    public function testInvokeUsesCorrectCacheKey(): void
    {
        $collectors = [
            new Collector(
                name: 'test-collector',
                displayName: [
                    'en' => 'Test',
                ],
                description: [
                    'en' => 'Test',
                ],
                type: 'test',
                version: '1.0',
                iconUrl: 'url',
                parameters: [],
                returnTypes: [],
                supportStream: true,
                supportBatch: false,
                supportedSourceTypes: [],
            ),
        ];

        $this->providerGateway->setCollectors($collectors);

        // Call handler to populate cache
        ($this->handler)(new GetCollectorListAction());

        // Verify cache contains the expected key
        $cacheItem = $this->cache->getItem('collectors_list');
        self::assertTrue($cacheItem->isHit(), 'Cache should contain collectors_list key');
        self::assertEquals($collectors, $cacheItem->get());
    }

    public function testInvokeCachesPersistsBetweenCalls(): void
    {
        $collectors = [
            new Collector(
                name: 'test-collector',
                displayName: [
                    'en' => 'Test',
                ],
                description: [
                    'en' => 'Test',
                ],
                type: 'test',
                version: '1.0',
                iconUrl: 'url',
                parameters: [],
                returnTypes: [],
                supportStream: true,
                supportBatch: false,
                supportedSourceTypes: [],
            ),
        ];

        $this->providerGateway->setCollectors($collectors);

        // First call populates cache
        $result1 = ($this->handler)(new GetCollectorListAction());

        // Change gateway to return empty list
        $this->providerGateway->setCollectors([]);

        // Subsequent calls should still return cached collectors
        $result2 = ($this->handler)(new GetCollectorListAction());
        $result3 = ($this->handler)(new GetCollectorListAction());

        // All results should be equal (same data)
        self::assertEquals($result1, $result2);
        self::assertEquals($result2, $result3);
        self::assertCount(1, $result2);
        self::assertEquals('test-collector', $result2[0]->name);
    }
}
