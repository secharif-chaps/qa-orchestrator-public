<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus\Client;

use App\Application\Collect\Auth\AuthenticateAction;
use App\Application\Collect\Auth\RefreshTokenAction;
use App\Domain\Collect\Auth\AccessToken;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\Exception\CollectHttpException;
use App\Infrastructure\Collect\Bakus\Client\BakusAbstractClient;
use App\Infrastructure\Collect\Bakus\Client\BakusHttpClient;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(BakusHttpClient::class)]
#[CoversClass(BakusAbstractClient::class)]
class BakusHttpClientTest extends TestCase
{
    private NullMessageBus $messageBus;
    private AccessToken $accessToken;

    protected function setUp(): void
    {
        $this->messageBus = new NullMessageBus();
        $this->accessToken = new AccessToken('test_token', time() + 3600, 'refresh_token');
    }

    public function testRequestSuccessful(): void
    {
        $this->messageBus->fakeHandler = fn (object $message): AccessToken => $this->accessToken;

        $mockResponse = new MockResponse(json_encode([
            'foo' => 'bar',
        ], \JSON_THROW_ON_ERROR), [
            'http_code' => Response::HTTP_OK,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $bakusClient = new BakusHttpClient('https://api.example.com', $httpClient, $this->messageBus);

        $result = $bakusClient->request('GET', '/test');

        $this->assertSame([
            'foo' => 'bar',
        ], $result);
        $this->assertSame('GET', $mockResponse->getRequestMethod());
        $this->assertSame('https://api.example.com/test', $mockResponse->getRequestUrl());
    }

    public function testRequestReturnsNullForNoContent(): void
    {
        $this->messageBus->fakeHandler = fn (object $message): AccessToken => $this->accessToken;

        $mockResponse = new MockResponse('', [
            'http_code' => Response::HTTP_NO_CONTENT,
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $bakusClient = new BakusHttpClient('https://api.example.com', $httpClient, $this->messageBus);

        $result = $bakusClient->request('POST', 'test', [
            'json' => [
                'data' => 'value',
            ],
        ]);

        $this->assertNull($result);
    }

    public function testRequestAsString(): void
    {
        $this->messageBus->fakeHandler = fn (object $message): AccessToken => $this->accessToken;

        $mockResponse = new MockResponse('some content', [
            'http_code' => Response::HTTP_OK,
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $bakusClient = new BakusHttpClient('https://api.example.com', $httpClient, $this->messageBus);

        $result = $bakusClient->request('GET', '/test', [], false);

        $this->assertSame('some content', $result);
    }

    public function testRequestRetriesOnUnauthorizedAndSucceeds(): void
    {
        $refreshedToken = new AccessToken('new_token', time() + 3600, 'new_refresh');
        $this->messageBus->fakeHandler = function (object $message) use ($refreshedToken): ?AccessToken {
            if ($message instanceof AuthenticateAction) {
                return $this->accessToken;
            }
            if ($message instanceof RefreshTokenAction) {
                return $refreshedToken;
            }

            return null;
        };

        $responses = [
            new MockResponse('', [
                'http_code' => Response::HTTP_UNAUTHORIZED,
            ]),
            new MockResponse(json_encode([
                'foo' => 'bar',
            ], \JSON_THROW_ON_ERROR), [
                'http_code' => Response::HTTP_OK,
            ]),
        ];
        $httpClient = new MockHttpClient($responses);
        $bakusClient = new BakusHttpClient('https://api.example.com', $httpClient, $this->messageBus);

        $result = $bakusClient->request('GET', '/test');

        $this->assertSame([
            'foo' => 'bar',
        ], $result);
    }

    public function testRequestFailsAfterMaxRetries(): void
    {
        $this->expectException(CollectHttpException::class);

        $this->messageBus->fakeHandler = function (object $message): ?AccessToken {
            if ($message instanceof AuthenticateAction) {
                return $this->accessToken;
            }
            if ($message instanceof RefreshTokenAction) {
                return new AccessToken('new_token', time() + 3600, 'new_refresh');
            }

            return null;
        };

        $responses = [
            new MockResponse('', [
                'http_code' => Response::HTTP_UNAUTHORIZED,
            ]),
            new MockResponse('', [
                'http_code' => Response::HTTP_UNAUTHORIZED,
            ]),
            new MockResponse('', [
                'http_code' => Response::HTTP_UNAUTHORIZED,
            ]),
            new MockResponse('', [
                'http_code' => Response::HTTP_UNAUTHORIZED,
            ]),
        ];
        $httpClient = new MockHttpClient($responses);
        $bakusClient = new BakusHttpClient('https://api.example.com', $httpClient, $this->messageBus);

        $bakusClient->request('GET', '/test');
    }

    public function testRequestThrowsHttpExceptionOnClientError(): void
    {
        $this->expectException(CollectHttpException::class);

        $this->messageBus->fakeHandler = fn (object $message): AccessToken => $this->accessToken;

        $responses = new MockResponse('', [
            'http_code' => Response::HTTP_NOT_FOUND,
        ]);
        $httpClient = new MockHttpClient($responses);
        $bakusClient = new BakusHttpClient('https://api.example.com', $httpClient, $this->messageBus);

        $bakusClient->request('GET', '/test');
    }

    public function testRequestThrowsCollectExceptionOnTransportError(): void
    {
        $this->expectException(CollectException::class);

        $this->messageBus->fakeHandler = fn (object $message): AccessToken => $this->accessToken;

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')
->willThrowException(new TransportException('Network error'));
        $bakusClient = new BakusHttpClient('https://api.example.com', $httpClient, $this->messageBus);

        $bakusClient->request('GET', '/test');
    }

    public function testRequestWithExpiringToken(): void
    {
        $expiringToken = new AccessToken('expiring_token', time() + 10, 'refresh_token');
        $refreshedToken = new AccessToken('refreshed_token', time() + 3600, 'new_refresh');

        $this->messageBus->fakeHandler = function (object $message) use (
            $expiringToken,
            $refreshedToken
        ): ?AccessToken {
            if ($message instanceof AuthenticateAction) {
                return $expiringToken;
            }
            if ($message instanceof RefreshTokenAction) {
                return $refreshedToken;
            }

            return null;
        };

        $mockResponse = new MockResponse(json_encode([
            'foo' => 'bar',
        ], \JSON_THROW_ON_ERROR), [
            'http_code' => Response::HTTP_OK,
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $bakusClient = new BakusHttpClient('https://api.example.com', $httpClient, $this->messageBus);

        $result = $bakusClient->request('GET', '/test');

        $this->assertSame([
            'foo' => 'bar',
        ], $result);
    }

    public function testFailsIfAuthenticationFails(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to retrieve access token from authentication action.');

        $this->messageBus->fakeHandler = fn (object $message): \stdClass => new \stdClass();

        $httpClient = new MockHttpClient();
        $bakusClient = new BakusHttpClient('https://api.example.com', $httpClient, $this->messageBus);

        $bakusClient->request('GET', '/test');
    }

    public function testFailsIfTokenRefreshFails(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to retrieve access token from authentication action.');

        $expiringToken = new AccessToken('expiring_token', time() + 10, 'refresh_token');

        $this->messageBus->fakeHandler = function (object $message) use ($expiringToken): AccessToken|\stdClass|null {
            if ($message instanceof AuthenticateAction) {
                return $expiringToken;
            }
            if ($message instanceof RefreshTokenAction) {
                return new \stdClass(); // Fail refresh
            }

            return null;
        };

        $httpClient = new MockHttpClient(new MockResponse('', [
            'http_code' => Response::HTTP_OK,
        ]));
        $bakusClient = new BakusHttpClient('https://api.example.com', $httpClient, $this->messageBus);

        $bakusClient->request('GET', '/test');
    }
}
