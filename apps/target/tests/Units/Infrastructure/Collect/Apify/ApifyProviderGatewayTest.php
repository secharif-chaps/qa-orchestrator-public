<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify;

use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\Exception\CollectHttpException;
use App\Infrastructure\Collect\Apify\ApifyActorRunConfig;
use App\Infrastructure\Collect\Apify\ApifyCollectTaskMapper;
use App\Infrastructure\Collect\Apify\ApifyProviderGateway;
use App\Infrastructure\Collect\Apify\ApifyStatusMapper;
use App\Infrastructure\Collect\Apify\Client\ApifyHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(ApifyProviderGateway::class)]
class ApifyProviderGatewayTest extends TestCase
{
    private ApifyHttpClient&Stub $apifyClient;
    private ApifyCollectTaskMapper&Stub $mapper;
    private ApifyStatusMapper $statusMapper;
    private ApifyProviderGateway $gateway;

    protected function setUp(): void
    {
        $this->apifyClient = $this->createStub(ApifyHttpClient::class);
        $this->mapper = $this->createStub(ApifyCollectTaskMapper::class);
        $this->statusMapper = new ApifyStatusMapper();
        $this->buildGateway();
    }

    private function buildGateway(): void
    {
        $this->gateway = new ApifyProviderGateway($this->apifyClient, $this->mapper, $this->statusMapper);
    }

    public function testCreateTaskSuccess(): void
    {
        $collectTask = $this->createStub(CollectTask::class);
        $config = new ApifyActorRunConfig(
            apifyActorId: 'myActor',
            input: [
                'url' => 'https://example.com',
            ],
            queryParams: [
                'maxTotalChargeUsd' => '0.10',
            ],
        );

        $this->mapper->method('mapToActorRun')
            ->willReturn($config);

        $this->apifyClient->method('request')
            ->willReturn([
                'data' => [
                    'id' => 'run123',
                    'status' => 'READY',
                ],
            ]);

        $taskId = $this->gateway->createTask($collectTask);

        $this->assertSame('myActor:run123', $taskId);
    }

    public function testCreateTaskHttpError(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to create task in Apify: HTTP Error');

        $collectTask = $this->createStub(CollectTask::class);
        $config = new ApifyActorRunConfig('myActor', [], []);

        $this->mapper->method('mapToActorRun')
            ->willReturn($config);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')
            ->willReturn(Response::HTTP_INTERNAL_SERVER_ERROR);
        $httpException = new CollectHttpException($response, 'HTTP Error');

        $this->apifyClient->method('request')
            ->willThrowException($httpException);

        $this->gateway->createTask($collectTask);
    }

    public function testCreateTaskMissingId(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to create task in Apify: Missing "data.id" in response.');

        $collectTask = $this->createStub(CollectTask::class);
        $config = new ApifyActorRunConfig('myActor', [], []);

        $this->mapper->method('mapToActorRun')
            ->willReturn($config);

        $this->apifyClient->method('request')
            ->willReturn([
                'data' => [
                    'status' => 'READY',
                ],
            ]);

        $this->gateway->createTask($collectTask);
    }

    public function testCreateTaskCachesInitialStatus(): void
    {
        $collectTask = $this->createStub(CollectTask::class);
        $config = new ApifyActorRunConfig('myActor', [], []);

        $this->mapper->method('mapToActorRun')
            ->willReturn($config);

        $this->apifyClient->method('request')
            ->willReturn([
                'data' => [
                    'id' => 'run123',
                    'status' => 'READY',
                ],
            ]);

        $taskId = $this->gateway->createTask($collectTask);

        // The status should be cached; getTaskStatus should NOT call the API
        $status = $this->gateway->getTaskStatus($taskId);

        $this->assertSame(CollectTaskStatus::QUEUED, $status);
    }

    public function testGetTaskStatusSuccess(): void
    {
        $this->apifyClient->method('request')
            ->willReturn([
                'data' => [
                    'id' => 'run123',
                    'status' => 'RUNNING',
                ],
            ]);

        $status = $this->gateway->getTaskStatus('myActor:run123');

        $this->assertSame(CollectTaskStatus::RUNNING, $status);
    }

    public function testGetTaskStatusCached(): void
    {
        // First call populates cache via createTask
        $collectTask = $this->createStub(CollectTask::class);
        $config = new ApifyActorRunConfig('myActor', [], []);
        $this->mapper->method('mapToActorRun')
            ->willReturn($config);

        $apifyClientMock = $this->createMock(ApifyHttpClient::class);
        $this->apifyClient = $apifyClientMock;
        $this->buildGateway();

        // createTask calls request once
        $apifyClientMock->expects($this->once())
            ->method('request')
            ->willReturn([
                'data' => [
                    'id' => 'run123',
                    'status' => 'READY',
                ],
            ]);

        $taskId = $this->gateway->createTask($collectTask);

        // getTaskStatus should use cache, no extra HTTP call
        $status = $this->gateway->getTaskStatus($taskId);
        $this->assertSame(CollectTaskStatus::QUEUED, $status);
    }

    public function testGetTaskStatusHttpError(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to retrieve task status from Apify: HTTP Error');

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')
            ->willReturn(Response::HTTP_NOT_FOUND);
        $httpException = new CollectHttpException($response, 'HTTP Error');

        $this->apifyClient->method('request')
            ->willThrowException($httpException);

        $this->gateway->getTaskStatus('myActor:run123');
    }

    public function testGetTaskStatusPreservesHttpExceptionCode(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')
            ->willReturn(Response::HTTP_NOT_FOUND);
        $httpException = new CollectHttpException($response, 'Not Found', 404);

        $this->apifyClient->method('request')
            ->willThrowException($httpException);

        try {
            $this->gateway->getTaskStatus('myActor:run123');
        } catch (CollectException $e) {
            $this->assertSame(404, $e->getCode());
            $this->assertSame($httpException, $e->getPrevious());
        }
    }

    public function testGetTaskStatusInvalidResponse(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to retrieve task status from Apify');

        $this->apifyClient->method('request')
            ->willReturn([
                'data' => [
                    'id' => 'run123',
                ],
            ]);

        $this->gateway->getTaskStatus('myActor:run123');
    }

    public function testCancelTaskSuccess(): void
    {
        $this->expectNotToPerformAssertions();

        $this->gateway->cancelTask('myActor:run123');
    }

    public function testCancelTaskHttpError(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to cancel task in Apify: HTTP Error');

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')
            ->willReturn(Response::HTTP_INTERNAL_SERVER_ERROR);
        $httpException = new CollectHttpException($response, 'HTTP Error');

        $this->apifyClient->method('request')
            ->willThrowException($httpException);

        $this->gateway->cancelTask('myActor:run123');
    }

    public function testGetCollectorsReturnsListFromConfig(): void
    {
        $this->mapper->method('getActorMapping')
            ->willReturn([
                'rss_feed' => 'apify/rss-scraper',
                'blog' => 'apify/blog-scraper',
            ]);

        $collectors = $this->gateway->getCollectors();

        $this->assertCount(2, $collectors);
        $this->assertSame('apify/rss-scraper', $collectors[0]->name);
        $this->assertSame('apify/blog-scraper', $collectors[1]->name);
        $this->assertSame('apify', $collectors[0]->type);
        $this->assertSame('apify', $collectors[1]->type);
    }

    public function testGetCollectorsSkipsInvalidSourceTypes(): void
    {
        $this->mapper->method('getActorMapping')
            ->willReturn([
                'rss_feed' => 'apify/rss-scraper',
                'invalid_type' => 'apify/invalid-scraper',
            ]);

        $collectors = $this->gateway->getCollectors();

        // Only the valid source type should be included
        $this->assertCount(1, $collectors);
        $this->assertSame('apify/rss-scraper', $collectors[0]->name);
    }

    public function testGetCollectorsReturnsEmptyForEmptyMapping(): void
    {
        $this->mapper->method('getActorMapping')
            ->willReturn([]);

        $collectors = $this->gateway->getCollectors();

        $this->assertEmpty($collectors);
    }
}
