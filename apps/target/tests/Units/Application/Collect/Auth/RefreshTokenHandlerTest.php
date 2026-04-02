<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect\Auth;

use App\Application\Collect\Auth\RefreshTokenAction;
use App\Application\Collect\Auth\RefreshTokenHandler;
use App\Domain\Collect\Auth\AccessToken;
use App\Domain\Collect\Auth\AuthenticationException;
use App\Tests\Units\Infrastructure\Collect\Auth\NullAuthenticationClient;
use App\Tests\Units\Infrastructure\Encryption\NullEncryptor;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class RefreshTokenHandlerTest extends TestCase
{
    private NullAuthenticationClient $authClient;
    private FilesystemAdapter $cache;
    private RefreshTokenHandler $handler;

    protected function setUp(): void
    {
        $this->authClient = new NullAuthenticationClient();
        $this->cache = new FilesystemAdapter('test_refresh', 0, sys_get_temp_dir());
        $this->handler = new RefreshTokenHandler(
            $this->authClient,
            $this->cache,
            new NullLogger(),
            new NullEncryptor(),
            new Serializer([new ObjectNormalizer(), new JsonSerializableNormalizer()], [new JsonEncoder()]),
        );
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
    }

    public function testSuccessfulTokenRefresh(): void
    {
        $action = new RefreshTokenAction('test-provider');
        $token = ($this->handler)($action);

        $this->assertSame('test-access-token', $token->token);
    }

    public function testTokenRefreshFailure(): void
    {
        $this->authClient->setShouldFailRefresh(true);

        $action = new RefreshTokenAction('test-provider');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Failed to refresh token for provider "test-provider"');

        ($this->handler)($action);
    }

    public function testCacheIsDeletedOnRefresh(): void
    {
        $action = new RefreshTokenAction('test-provider');

        // Pre-populate cache with some data
        $cacheKey = \sprintf('collect_auth_token_%s', $action->provider);
        $this->cache->get($cacheKey, fn () => new AccessToken('old-token', time() - 3600));

        // Verify cache has data
        $this->assertTrue($this->cache->hasItem($cacheKey));

        // Refresh token should clear cache and create new entry
        $token = ($this->handler)($action);

        $this->assertNotSame('old-token', $token->token);
        $this->assertTrue($this->cache->hasItem($cacheKey));
    }

    public function testTokenIsCachedAfterRefresh(): void
    {
        // Fix timestamp to ensure tokens are identical when cached
        $this->authClient->setFixedTimestamp(1757694000);

        $action = new RefreshTokenAction('test-provider');

        // First call should refresh and cache
        $token1 = ($this->handler)($action);

        // Reset client and make it fail - but cache should still work
        $this->authClient->setToken(null);

        // Second call should return cached token without calling authClient
        $token2 = ($this->handler)($action);

        $this->assertEquals($token1, $token2);
    }

    public function testCacheIsDeletedOnFailure(): void
    {
        $action = new RefreshTokenAction('test-provider');

        // First successful refresh to populate cache
        ($this->handler)($action);

        $cacheKey = \sprintf('collect_auth_token_%s', $action->provider);
        $this->assertTrue($this->cache->hasItem($cacheKey));

        // Make refresh fail
        $this->authClient->setShouldFailRefresh(true);

        try {
            ($this->handler)($action);
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            // Expected exception
        }

        // Cache should be cleared on failure
        $this->assertFalse($this->cache->hasItem($cacheKey));
    }
}
