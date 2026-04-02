<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect\Auth;

use App\Application\Collect\Auth\AuthenticateAction;
use App\Application\Collect\Auth\AuthenticateHandler;
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

class AuthenticateHandlerTest extends TestCase
{
    private NullAuthenticationClient $authClient;
    private FilesystemAdapter $cache;
    private AuthenticateHandler $handler;

    protected function setUp(): void
    {
        $this->authClient = new NullAuthenticationClient();
        $this->cache = new FilesystemAdapter('test_auth', 0, sys_get_temp_dir());
        $this->handler = new AuthenticateHandler(
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

    public function testSuccessfulAuthentication(): void
    {
        $action = new AuthenticateAction('test-provider');
        $token = ($this->handler)($action);

        $this->assertSame('test-access-token', $token->token);
        $this->assertTrue($this->authClient->isAuthenticated());
    }

    public function testAuthenticationFailure(): void
    {
        $this->authClient->setShouldFailAuthentication(true);

        $action = new AuthenticateAction('test-provider');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Failed to authenticate with provider "test-provider"');

        ($this->handler)($action);
    }

    public function testTokenCaching(): void
    {
        $action = new AuthenticateAction('test-provider');

        // First call should authenticate
        $token1 = ($this->handler)($action);

        // Reset the client to verify caching
        $this->authClient->setToken(null);
        $this->authClient->setShouldFailAuthentication(true);

        // Second call should return cached token, not fail
        $token2 = ($this->handler)($action);

        $this->assertEquals($token1, $token2);
    }

    public function testForceRefresh(): void
    {
        // First authentication
        $action1 = new AuthenticateAction('test-provider');
        $token1 = ($this->handler)($action1);

        // Force refresh should bypass cache
        $this->authClient->forceNewToken();
        $action2 = new AuthenticateAction('test-provider', forceRefresh: true);
        $token2 = ($this->handler)($action2);

        // Tokens should be different instances (new authentication)
        $this->assertNotSame($token1, $token2);
        $this->assertGreaterThan($token1->expiresAt, $token2->expiresAt);
    }
}
