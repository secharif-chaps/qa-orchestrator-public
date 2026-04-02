<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Cache;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Integration tests to verify Valkey cache backend connectivity and operations.
 * Validates that the protocol-compatible migration from Redis to Valkey works correctly.
 */
class ValkeyCacheIntegrationTest extends KernelTestCase
{
    private CacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $container = static::getContainer();
        /** @var CacheInterface $cache */
        $cache = $container->get(CacheInterface::class);
        $this->cache = $cache;
    }

    public function testCacheSetAndGetOperations(): void
    {
        $testKey = 'test_valkey_set_get_' . uniqid();
        $testValue = 'test_value_' . time();

        // Test set operation
        $result = $this->cache->get($testKey, function (ItemInterface $item) use ($testValue): string {
            $item->expiresAfter(60);

            return $testValue;
        });

        $this->assertSame($testValue, $result);

        // Test get operation (should retrieve from cache)
        $cachedResult = $this->cache->get($testKey, fn (): string => 'should_not_be_called');

        $this->assertSame($testValue, $cachedResult);
    }

    public function testCacheDeleteOperation(): void
    {
        $testKey = 'test_valkey_delete_' . uniqid();
        $testValue = 'value_to_delete';

        // Set a value
        $this->cache->get($testKey, function (ItemInterface $item) use ($testValue): string {
            $item->expiresAfter(60);

            return $testValue;
        });

        // Delete the cache entry
        $this->cache->delete($testKey);

        // Verify it was deleted (callback should be called now)
        $newValue = 'new_value_after_delete';
        $result = $this->cache->get($testKey, function (ItemInterface $item) use ($newValue): string {
            $item->expiresAfter(60);

            return $newValue;
        });

        $this->assertSame($newValue, $result);
    }

    public function testCacheConnectionIsOperational(): void
    {
        // This test validates that the cache service is properly configured
        // and can perform basic operations without connection errors
        $testKey = 'test_valkey_connection_' . uniqid();

        $result = $this->cache->get($testKey, function (ItemInterface $item): string {
            $item->expiresAfter(10);

            return 'connection_test_value';
        });

        $this->assertSame('connection_test_value', $result);

        // Cleanup
        $this->cache->delete($testKey);
    }
}
