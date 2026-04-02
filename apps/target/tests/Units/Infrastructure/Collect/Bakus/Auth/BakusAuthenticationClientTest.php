<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus\Auth;

use App\Domain\Collect\Auth\AccessToken;
use App\Domain\Collect\Auth\AuthenticationCredentials;
use App\Domain\Collect\Auth\AuthenticationException;
use App\Infrastructure\Collect\Bakus\Auth\BakusAuthenticationClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class BakusAuthenticationClientTest extends TestCase
{
    private AuthenticationCredentials $credentials;

    protected function setUp(): void
    {
        $this->credentials = new AuthenticationCredentials(
            clientId: 'test-client',
            clientSecret: 'test-secret',
            username: 'test-user',
            password: 'test-pass',
            authUrl: 'https://auth.bakus.com/token'
        );

        parent::setUp();
    }

    public function testSuccessfulAuthentication(): void
    {
        $responseData = [
            'access_token' => 'test-access-token',
            'expires_in' => 3600,
            'refresh_token' => 'test-refresh-token',
            'refresh_expires_in' => 7200,
            'token_type' => 'Bearer',
            'scope' => 'read write',
        ];

        $mockResponse = new MockResponse((string) json_encode($responseData));
        $httpClient = new MockHttpClient($mockResponse);

        $client = new BakusAuthenticationClient($this->credentials, $httpClient, new NullLogger());

        $token = $client->authenticate();

        $this->assertSame('test-access-token', $token->token);
        $this->assertSame('test-refresh-token', $token->refreshToken);
        $this->assertSame('Bearer', $token->tokenType);
        $this->assertTrue($client->isAuthenticated());
    }

    public function testAuthenticationFailureWithInvalidCredentials(): void
    {
        $mockResponse = new MockResponse('{"error": "invalid_client"}', [
            'http_code' => 401,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new BakusAuthenticationClient($this->credentials, $httpClient, new NullLogger());

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials for provider "bakus"');

        $client->authenticate();
    }

    public function testAuthenticationFailureWithServerError(): void
    {
        $mockResponse = new MockResponse('Internal Server Error', [
            'http_code' => 500,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new BakusAuthenticationClient($this->credentials, $httpClient, new NullLogger(), 2, 1);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Provider "bakus" is temporarily unavailable');

        $client->authenticate();
    }

    public function testRefreshIfNeededWithValidToken(): void
    {
        $responseData = [
            'access_token' => 'test-access-token',
            'expires_in' => 3600, // 1 hour from now
            'refresh_token' => 'test-refresh-token',
            'refresh_expires_in' => 7200,
            'token_type' => 'Bearer',
            'scope' => 'read write',
        ];

        $mockResponse = new MockResponse((string) json_encode($responseData));
        $httpClient = new MockHttpClient($mockResponse);

        $client = new BakusAuthenticationClient($this->credentials, $httpClient, new NullLogger());

        // First authenticate
        $originalToken = $client->authenticate();

        // Should return same token as it's not expiring soon
        $refreshedToken = $client->refreshIfNeeded();

        $this->assertSame($originalToken, $refreshedToken);
    }

    public function testRefreshIfNeededWithExpiringSoonToken(): void
    {
        // Create a token that expires in 2 minutes (less than 5 minute buffer)
        $validToken = new AccessToken(
            token: 'expiring-token',
            expiresAt: time() + 120,
            refreshToken: 'valid-refresh-token',
            refreshExpiresAt: time() + 7200
        );

        $refreshResponseData = [
            'access_token' => 'new-access-token',
            'expires_in' => 3600,
            'refresh_token' => 'new-refresh-token',
            'refresh_expires_in' => 7200,
            'token_type' => 'Bearer',
            'scope' => 'read write',
        ];

        $mockResponse = new MockResponse((string) json_encode($refreshResponseData));
        $httpClient = new MockHttpClient($mockResponse);

        $client = new BakusAuthenticationClient($this->credentials, $httpClient, new NullLogger());

        // Set the expiring token manually
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('currentToken');
        $property->setValue($client, $validToken);

        $newToken = $client->refreshIfNeeded();

        $this->assertNotSame($validToken, $newToken);
        $this->assertSame('new-access-token', $newToken->token);
    }

    public function testGetCurrentTokenReturnsNull(): void
    {
        $httpClient = new MockHttpClient();

        $client = new BakusAuthenticationClient($this->credentials, $httpClient, new NullLogger());

        $this->assertNull($client->getCurrentToken());
        $this->assertFalse($client->isAuthenticated());
    }

    public function testIsAuthenticatedWithExpiredToken(): void
    {
        $expiredToken = new AccessToken(
            token: 'expired-token',
            expiresAt: time() - 100 // Expired 100 seconds ago
        );

        $httpClient = new MockHttpClient();

        $client = new BakusAuthenticationClient($this->credentials, $httpClient, new NullLogger());

        // Set expired token manually
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('currentToken');
        $property->setAccessible(true);
        $property->setValue($client, $expiredToken);

        $this->assertFalse($client->isAuthenticated());
    }

    public function testRetryLogicWithServerErrors(): void
    {
        // Test that server errors (500) are retried and eventually succeed
        $responses = [
            new MockResponse('Internal Server Error', [
                'http_code' => 500,
            ]),
            new MockResponse('Internal Server Error', [
                'http_code' => 500,
            ]),
            new MockResponse((string) json_encode([
                'access_token' => 'success-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]), [
                'http_code' => 200,
            ]), // Success on third try
        ];

        $httpClient = new MockHttpClient($responses);

        $client = new BakusAuthenticationClient($this->credentials, $httpClient, new NullLogger(), 3, 1);

        $token = $client->authenticate();

        $this->assertSame('success-token', $token->token);
    }

    public function testAuthenticationFailureWithMalformedResponse(): void
    {
        $mockResponse = new MockResponse((string) json_encode([
            'access_token' => null, // Invalid token
            'expires_in' => 3600,
        ]));
        $httpClient = new MockHttpClient($mockResponse);

        $client = new BakusAuthenticationClient($this->credentials, $httpClient, new NullLogger());

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Failed to authenticate with provider "bakus"');

        $client->authenticate();
    }

    public function testAuthenticationFailureWithMissingAccessToken(): void
    {
        $mockResponse = new MockResponse((string) json_encode([
            'expires_in' => 3600,
            // Missing access_token
        ]));
        $httpClient = new MockHttpClient($mockResponse);

        $client = new BakusAuthenticationClient($this->credentials, $httpClient, new NullLogger());

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Failed to authenticate with provider "bakus"');

        $client->authenticate();
    }

    public function testRefreshFailureWithMalformedResponse(): void
    {
        // Set up an expired token that needs refresh
        $expiredToken = new AccessToken(
            token: 'expired-token',
            expiresAt: time() + 120, // Less than 5 minute buffer
            refreshToken: 'valid-refresh-token',
            refreshExpiresAt: time() + 7200
        );

        $mockResponse = new MockResponse((string) json_encode([
            'access_token' => null, // Invalid token
            'expires_in' => 3600,
        ]));
        $httpClient = new MockHttpClient($mockResponse);

        $client = new BakusAuthenticationClient($this->credentials, $httpClient, new NullLogger());

        // Set the expired token manually
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('currentToken');
        $property->setAccessible(true);
        $property->setValue($client, $expiredToken);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Failed to refresh token for provider "bakus"');

        $client->refreshIfNeeded();
    }
}
