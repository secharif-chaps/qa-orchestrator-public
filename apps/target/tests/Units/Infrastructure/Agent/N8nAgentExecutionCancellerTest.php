<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Agent;

use App\Infrastructure\Agent\N8nAgentExecutionCanceller;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[CoversClass(N8nAgentExecutionCanceller::class)]
class N8nAgentExecutionCancellerTest extends TestCase
{
    private function createCanceller(MockHttpClient $httpClient): N8nAgentExecutionCanceller
    {
        return new N8nAgentExecutionCanceller($httpClient, 'http://n8n:5678', 'test-api-key', new NullLogger());
    }

    public function testCancelStopsExecution(): void
    {
        $stopResponse = new MockResponse('', [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient([$stopResponse]);

        $canceller = $this->createCanceller($httpClient);

        $canceller->cancel('exec-123');

        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertSame('POST', $stopResponse->getRequestMethod());
        $this->assertSame('http://n8n:5678/api/v1/executions/exec-123/stop', $stopResponse->getRequestUrl());
        $this->assertStringContainsString(
            'X-N8N-API-KEY: test-api-key',
            implode("\r\n", $stopResponse->getRequestOptions()['headers'])
        );
    }

    public function testCancelClientErrorDoesNotThrow(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 404,
            ]),
        ]);

        $canceller = $this->createCanceller($httpClient);

        $canceller->cancel('exec-not-found');
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testCancelServerErrorDoesNotThrow(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 500,
            ]),
        ]);

        $canceller = $this->createCanceller($httpClient);

        $canceller->cancel('exec-server-error');
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testCancelTransportErrorDoesNotThrow(): void
    {
        $httpClient = new MockHttpClient([
            static function (): never {
                throw new class(
                    'Connection refused'
                ) extends \RuntimeException implements TransportExceptionInterface {};
            },
        ]);

        $canceller = $this->createCanceller($httpClient);

        $canceller->cancel('exec-unreachable');
        $this->expectNotToPerformAssertions();
    }

    public function testCancelSkippedWhenApiUrlIsEmpty(): void
    {
        $httpClient = new MockHttpClient();

        $canceller = new N8nAgentExecutionCanceller($httpClient, '', 'key', new NullLogger());

        $canceller->cancel('exec-123');

        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testCancelSkippedWhenApiKeyIsEmpty(): void
    {
        $httpClient = new MockHttpClient();

        $canceller = new N8nAgentExecutionCanceller($httpClient, 'http://n8n:5678', '', new NullLogger());

        $canceller->cancel('exec-123');

        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testMultipleCancelsDoNotRequireAuthentication(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 200,
            ]),
            new MockResponse('', [
                'http_code' => 200,
            ]),
        ]);

        $canceller = $this->createCanceller($httpClient);

        $canceller->cancel('exec-1');
        $canceller->cancel('exec-2');

        $this->assertSame(2, $httpClient->getRequestsCount());
    }
}
