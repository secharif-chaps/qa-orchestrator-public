<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Gateway;

use App\Infrastructure\Gateway\GlobalServiceRegistryClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[CoversClass(GlobalServiceRegistryClient::class)]
class GlobalServiceRegistryClientTest extends TestCase
{
    private function createClient(
        MockHttpClient $httpClient,
        string $globalServiceUrl = 'http://global-service:8000/api',
        string $internalJwtSecret = 'test-secret-key-32chars-long!!!!',
    ): GlobalServiceRegistryClient {
        return new GlobalServiceRegistryClient($httpClient, $globalServiceUrl, $internalJwtSecret, new NullLogger());
    }

    private function jsonBody(string $action): string
    {
        return (string) json_encode([
            'action' => $action,
        ]);
    }

    public function testAnnounceHappyPath(): void
    {
        $announceResponse = new MockResponse($this->jsonBody('rediscovered'), [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient([$announceResponse]);

        $client = $this->createClient($httpClient);
        $action = $client->announce('a' . str_repeat('0', 63));

        $this->assertSame('rediscovered', $action);
        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertSame('POST', $announceResponse->getRequestMethod());
        $this->assertSame(
            'http://global-service:8000/api/internal/registry/announce/target',
            $announceResponse->getRequestUrl()
        );
    }

    public function testAnnounceUnchangedHash(): void
    {
        $announceResponse = new MockResponse($this->jsonBody('unchanged'), [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient([$announceResponse]);

        $client = $this->createClient($httpClient);
        $action = $client->announce(str_repeat('a', 64));

        $this->assertSame('unchanged', $action);
    }

    public function testAnnounceSendsInternalAuthHeader(): void
    {
        $announceResponse = new MockResponse($this->jsonBody('unchanged'), [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient([$announceResponse]);

        $client = $this->createClient($httpClient);
        $client->announce(str_repeat('a', 64));

        $requestHeaders = $announceResponse->getRequestOptions()['headers'];
        $authHeader = '';
        foreach ($requestHeaders as $header) {
            if (str_starts_with($header, 'Authorization:')) {
                $authHeader = $header;
                break;
            }
        }
        $this->assertStringContainsString('Internal ', $authHeader);
    }

    public function testAnnounceSendsHashInPayload(): void
    {
        $announceResponse = new MockResponse($this->jsonBody('unchanged'), [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient([$announceResponse]);

        $hash = hash('sha256', 'test-schema');
        $client = $this->createClient($httpClient);
        $client->announce($hash);

        /** @var array{openapi_hash: string} $body */
        $body = json_decode((string) $announceResponse->getRequestOptions()['body'], true);
        $this->assertSame($hash, $body['openapi_hash']);
    }

    public function testAnnounceStripsTrailingSlashFromUrl(): void
    {
        $announceResponse = new MockResponse($this->jsonBody('unchanged'), [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient([$announceResponse]);

        $client = $this->createClient($httpClient, globalServiceUrl: 'http://global-service:8000/api/');
        $client->announce(str_repeat('a', 64));

        $url = $announceResponse->getRequestUrl();
        $this->assertStringNotContainsString('//internal', $url);
        $this->assertStringEndsWith('/internal/registry/announce/target', $url);
    }

    public function testAnnounceSkippedWhenGlobalServiceUrlIsEmpty(): void
    {
        $httpClient = new MockHttpClient();

        $client = $this->createClient($httpClient, globalServiceUrl: '');
        $action = $client->announce(str_repeat('a', 64));

        $this->assertSame('skipped', $action);
        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testAnnounceSkippedWhenInternalJwtSecretIsEmpty(): void
    {
        $httpClient = new MockHttpClient();

        $client = $this->createClient($httpClient, internalJwtSecret: '');
        $action = $client->announce(str_repeat('a', 64));

        $this->assertSame('skipped', $action);
        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testAnnounceReturnsErrorOnTransportFailure(): void
    {
        $httpClient = new MockHttpClient([
            static function (): never {
                throw new class(
                    'Connection refused'
                ) extends \RuntimeException implements TransportExceptionInterface {};
            },
        ]);

        $client = $this->createClient($httpClient);
        $action = $client->announce(str_repeat('a', 64));

        $this->assertSame('error', $action);
    }

    public function testAnnounceReturnsErrorOnHttpError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Service Unavailable', [
                'http_code' => 503,
            ]),
        ]);

        $client = $this->createClient($httpClient);
        $action = $client->announce(str_repeat('a', 64));

        $this->assertSame('error', $action);
    }
}
