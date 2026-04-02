<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify\Client;

use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\Exception\CollectHttpException;
use App\Infrastructure\Collect\Apify\Client\ApifyHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ApifyHttpClient::class)]
class ApifyHttpClientTest extends TestCase
{
    private const string API_URL = 'https://api.apify.com/v2';
    private const string API_TOKEN = 'apify_api_test_token_123';

    public function testRequestSuccessful(): void
    {
        $mockResponse = new MockResponse((string) json_encode([
            'data' => [
                'id' => '123',
            ],
        ]), [
            'http_code' => Response::HTTP_OK,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = $this->createClient($httpClient);
        $result = $client->request('GET', '/users/me');

        $this->assertSame([
            'data' => [
                'id' => '123',
            ],
        ], $result);
    }

    public function testRequestReturnsStringContent(): void
    {
        $mockResponse = new MockResponse('raw content', [
            'http_code' => Response::HTTP_OK,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = $this->createClient($httpClient);
        $result = $client->request('GET', '/some/path', [], false);

        $this->assertSame('raw content', $result);
    }

    public function testRequestReturnsNullOnNoContent(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => Response::HTTP_NO_CONTENT,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = $this->createClient($httpClient);
        $result = $client->request('DELETE', '/some/resource');

        $this->assertNull($result);
    }

    public function testRequestSendsBearerToken(): void
    {
        $mockResponse = new MockResponse((string) json_encode([
            'data' => [],
        ]), [
            'http_code' => Response::HTTP_OK,
        ]);
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($mockResponse) {
            $this->assertContains(
                'Authorization: Bearer ' . self::API_TOKEN,
                $options['normalized_headers']['authorization'],
            );

            return $mockResponse;
        });

        $client = $this->createClient($httpClient);
        $client->request('GET', '/users/me');
    }

    public function testRequestBuildsUrlCorrectly(): void
    {
        $mockResponse = new MockResponse((string) json_encode([]), [
            'http_code' => Response::HTTP_OK,
        ]);
        $httpClient = new MockHttpClient(function (string $method, string $url) use ($mockResponse) {
            $this->assertSame('https://api.apify.com/v2/acts/my-actor/runs', $url);

            return $mockResponse;
        });

        $client = $this->createClient($httpClient);
        $client->request('POST', '/acts/my-actor/runs');
    }

    public function testRequestRetriesOnRateLimit(): void
    {
        $responses = [
            new MockResponse('Rate limited', [
                'http_code' => Response::HTTP_TOO_MANY_REQUESTS,
                'response_headers' => [
                    'retry-after' => '0',
                ],
            ]),
            new MockResponse((string) json_encode([
                'data' => 'success',
            ]), [
                'http_code' => Response::HTTP_OK,
            ]),
        ];

        $httpClient = new MockHttpClient($responses);

        $client = $this->createClient($httpClient);
        $result = $client->request('GET', '/users/me');

        $this->assertSame([
            'data' => 'success',
        ], $result);
    }

    public function testRequestThrowsCollectHttpExceptionOnClientError(): void
    {
        $mockResponse = new MockResponse('Not Found', [
            'http_code' => Response::HTTP_NOT_FOUND,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = $this->createClient($httpClient);

        $this->expectException(CollectHttpException::class);

        $client->request('GET', '/unknown');
    }

    public function testRequestThrowsCollectExceptionOnTransportError(): void
    {
        $httpClient = new MockHttpClient(function () {
            return new MockResponse('', [
                'error' => 'Connection refused',
            ]);
        });

        $client = $this->createClient($httpClient);

        $this->expectException(CollectException::class);

        $client->request('GET', '/users/me');
    }

    public function testRequestThrowsAfterMaxRetryAttempts(): void
    {
        $responses = [
            new MockResponse('Rate limited', [
                'http_code' => Response::HTTP_TOO_MANY_REQUESTS,
                'response_headers' => [
                    'retry-after' => '0',
                ],
            ]),
            new MockResponse('Rate limited', [
                'http_code' => Response::HTTP_TOO_MANY_REQUESTS,
                'response_headers' => [
                    'retry-after' => '0',
                ],
            ]),
            new MockResponse('Rate limited', [
                'http_code' => Response::HTTP_TOO_MANY_REQUESTS,
                'response_headers' => [
                    'retry-after' => '0',
                ],
            ]),
            new MockResponse('Rate limited', [
                'http_code' => Response::HTTP_TOO_MANY_REQUESTS,
                'response_headers' => [
                    'retry-after' => '0',
                ],
            ]),
        ];

        $httpClient = new MockHttpClient($responses);
        $client = $this->createClient($httpClient);

        $this->expectException(CollectHttpException::class);

        $client->request('GET', '/users/me');
    }

    public function testRetryUsesDefaultDelayWhenHeaderMissing(): void
    {
        $responses = [
            new MockResponse('Rate limited', [
                'http_code' => Response::HTTP_TOO_MANY_REQUESTS,
            ]),
            new MockResponse((string) json_encode([
                'data' => 'ok',
            ]), [
                'http_code' => Response::HTTP_OK,
            ]),
        ];

        $httpClient = new MockHttpClient($responses);
        $client = $this->createClient($httpClient);

        $result = $client->request('GET', '/users/me');

        $this->assertSame([
            'data' => 'ok',
        ], $result);
    }

    public function testRequestThrowsOnUnauthorized(): void
    {
        $mockResponse = new MockResponse('{"error": "Unauthorized"}', [
            'http_code' => Response::HTTP_UNAUTHORIZED,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = $this->createClient($httpClient);

        $this->expectException(CollectHttpException::class);

        $client->request('GET', '/acts/my-actor/runs');
    }

    public function testRequestBuildsUrlWithoutLeadingSlash(): void
    {
        $mockResponse = new MockResponse((string) json_encode([]), [
            'http_code' => Response::HTTP_OK,
        ]);
        $httpClient = new MockHttpClient(function (string $method, string $url) use ($mockResponse) {
            $this->assertSame('https://api.apify.com/v2/users/me', $url);

            return $mockResponse;
        });

        $client = $this->createClient($httpClient);
        $client->request('GET', 'users/me');
    }

    private function createClient(MockHttpClient $httpClient): ApifyHttpClient
    {
        return new ApifyHttpClient(self::API_URL, self::API_TOKEN, $httpClient, new NullLogger());
    }
}
