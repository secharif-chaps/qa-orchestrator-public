<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify\Auth;

use App\Domain\Collect\Auth\AuthenticationException;
use App\Infrastructure\Collect\Apify\Auth\ApifyAuthenticationClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ApifyAuthenticationClient::class)]
class ApifyAuthenticationClientTest extends TestCase
{
    private const string API_URL = 'https://api.apify.com/v2';
    private const string API_TOKEN = 'apify_api_test_token_123';

    public function testSuccessfulAuthentication(): void
    {
        $responseData = [
            'data' => [
                'username' => 'test-user',
                'id' => 'user-123',
            ],
        ];

        $mockResponse = new MockResponse((string) json_encode($responseData));
        $httpClient = new MockHttpClient($mockResponse);

        $client = new ApifyAuthenticationClient(self::API_URL, self::API_TOKEN, $httpClient, new NullLogger());

        $token = $client->authenticate();

        $this->assertSame(self::API_TOKEN, $token->token);
        $this->assertSame('Bearer', $token->tokenType);
        $this->assertTrue($client->isAuthenticated());
        $this->assertSame($token, $client->getCurrentToken());
    }

    public function testAuthenticationVerifiesViaUsersMe(): void
    {
        $mockResponse = new MockResponse((string) json_encode([
            'data' => [
                'username' => 'test',
            ],
        ]));
        $httpClient = new MockHttpClient(function (string $method, string $url) use ($mockResponse) {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.apify.com/v2/users/me', $url);

            return $mockResponse;
        });

        $client = new ApifyAuthenticationClient(self::API_URL, self::API_TOKEN, $httpClient, new NullLogger());
        $client->authenticate();
    }

    public function testAuthenticationSendsBearerToken(): void
    {
        $mockResponse = new MockResponse((string) json_encode([
            'data' => [
                'username' => 'test',
            ],
        ]));
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($mockResponse) {
            $this->assertContains(
                'Authorization: Bearer ' . self::API_TOKEN,
                $options['normalized_headers']['authorization']
            );

            return $mockResponse;
        });

        $client = new ApifyAuthenticationClient(self::API_URL, self::API_TOKEN, $httpClient, new NullLogger());
        $client->authenticate();
    }

    public function testAuthenticationFailureWithInvalidToken(): void
    {
        $mockResponse = new MockResponse('{"error": "Unauthorized"}', [
            'http_code' => 401,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new ApifyAuthenticationClient(self::API_URL, 'invalid-token', $httpClient, new NullLogger());

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials for provider "apify"');

        $client->authenticate();
    }

    public function testAuthenticationFailureWithForbidden(): void
    {
        $mockResponse = new MockResponse('{"error": "Forbidden"}', [
            'http_code' => 403,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new ApifyAuthenticationClient(self::API_URL, self::API_TOKEN, $httpClient, new NullLogger());

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials for provider "apify"');

        $client->authenticate();
    }

    public function testAuthenticationFailureWithServerError(): void
    {
        $mockResponse = new MockResponse('Internal Server Error', [
            'http_code' => 500,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new ApifyAuthenticationClient(self::API_URL, self::API_TOKEN, $httpClient, new NullLogger());

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Failed to authenticate with provider "apify"');

        $client->authenticate();
    }

    public function testRefreshIfNeededAuthenticatesWhenNoToken(): void
    {
        $mockResponse = new MockResponse((string) json_encode([
            'data' => [
                'username' => 'test',
            ],
        ]));
        $httpClient = new MockHttpClient($mockResponse);

        $client = new ApifyAuthenticationClient(self::API_URL, self::API_TOKEN, $httpClient, new NullLogger());

        $token = $client->refreshIfNeeded();

        $this->assertSame(self::API_TOKEN, $token->token);
        $this->assertTrue($client->isAuthenticated());
    }

    public function testRefreshIfNeededReturnsCachedToken(): void
    {
        $mockResponse = new MockResponse((string) json_encode([
            'data' => [
                'username' => 'test',
            ],
        ]));
        $httpClient = new MockHttpClient($mockResponse);

        $client = new ApifyAuthenticationClient(self::API_URL, self::API_TOKEN, $httpClient, new NullLogger());

        $firstToken = $client->authenticate();
        $secondToken = $client->refreshIfNeeded();

        $this->assertSame($firstToken, $secondToken);
    }

    public function testGetCurrentTokenReturnsNullBeforeAuth(): void
    {
        $httpClient = new MockHttpClient();

        $client = new ApifyAuthenticationClient(self::API_URL, self::API_TOKEN, $httpClient, new NullLogger());

        $this->assertNull($client->getCurrentToken());
        $this->assertFalse($client->isAuthenticated());
    }
}
