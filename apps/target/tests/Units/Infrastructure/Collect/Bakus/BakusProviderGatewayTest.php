<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus;

use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\Exception\CollectHttpException;
use App\Domain\Collect\Exception\InvalidCollectorDefinitionException;
use App\Domain\Collect\Exception\NotSupportedCollectorException;
use App\Domain\Collect\ValueObject\Collector;
use App\Infrastructure\Collect\Bakus\BakusCollectTaskMapper;
use App\Infrastructure\Collect\Bakus\BakusProviderGateway;
use App\Infrastructure\Collect\Bakus\BakusStatusMapper;
use App\Infrastructure\Collect\Bakus\Client\BakusHttpClient;
use App\Infrastructure\Collect\Bakus\CollectorFactory;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\ResponseInterface;

class BakusProviderGatewayTest extends TestCase
{
    private BakusHttpClient&Stub $bakusClient;
    private BakusCollectTaskMapper&Stub $mapper;
    private BakusStatusMapper $statusMapper;
    private CollectorFactory&Stub $collectorFactory;
    private BakusProviderGateway $gateway;

    protected function setUp(): void
    {
        $this->bakusClient = $this->createStub(BakusHttpClient::class);
        $this->mapper = $this->createStub(BakusCollectTaskMapper::class);
        $this->statusMapper = new BakusStatusMapper();
        $this->collectorFactory = $this->createStub(CollectorFactory::class);
        $this->buildGateway();
    }

    private function buildGateway(): void
    {
        $this->gateway = new BakusProviderGateway(
            $this->bakusClient,
            $this->mapper,
            $this->statusMapper,
            $this->collectorFactory
        );
    }

    public function testCreateTaskSuccess(): void
    {
        $collectTask = $this->createStub(CollectTask::class);
        $query = [
            'key' => 'value',
        ];
        $response = [
            'id' => '12345',
        ];

        $this->mapper->method('mapCollectTaskToBakusQuery')
            ->willReturn($query);

        $this->bakusClient->method('request')
            ->willReturn($response);

        $taskId = $this->gateway->createTask($collectTask);

        $this->assertSame('12345', $taskId);
    }

    public function testCreateTaskThrowsExceptionOnMissingId(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to create task in Bakus: Missing "id" in response.');

        $collectTask = $this->createStub(CollectTask::class);
        $this->mapper->method('mapCollectTaskToBakusQuery')
            ->willReturn([]);
        $this->bakusClient->method('request')
            ->willReturn([
                'foo' => 'bar',
            ]);

        $this->gateway->createTask($collectTask);
    }

    public function testCreateTaskThrowsExceptionOnInvalidId(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to create task in Bakus: Invalid "id" in response.');

        $collectTask = $this->createStub(CollectTask::class);
        $this->mapper->method('mapCollectTaskToBakusQuery')
            ->willReturn([]);
        $this->bakusClient->method('request')
            ->willReturn([
                'id' => 'invalid',
            ]);

        $this->gateway->createTask($collectTask);
    }

    public function testCreateTaskThrowsExceptionOnHttpError(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to create task in Bakus: HTTP Error');

        $collectTask = $this->createStub(CollectTask::class);
        $this->mapper->method('mapCollectTaskToBakusQuery')
            ->willReturn([]);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')
            ->willReturn(Response::HTTP_INTERNAL_SERVER_ERROR);
        $httpException = new CollectHttpException($response, 'HTTP Error');

        $this->bakusClient->method('request')
            ->willThrowException($httpException);

        $this->gateway->createTask($collectTask);
    }

    public function testCancelTaskSuccess(): void
    {
        $this->expectNotToPerformAssertions();

        $this->gateway->cancelTask('123');
    }

    public function testCancelTaskThrowsExceptionOnHttpError(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to cancel task in Bakus: HTTP Error');

        $taskId = '123';
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')
            ->willReturn(Response::HTTP_INTERNAL_SERVER_ERROR);
        $httpException = new CollectHttpException($response, 'HTTP Error');

        $this->bakusClient->method('request')
            ->willThrowException($httpException);

        $this->gateway->cancelTask($taskId);
    }

    public function testGetCollectorsSuccess(): void
    {
        $bakusResponse = [
            [
                'name' => 'collector1',
                'type' => 'type1',
            ],
            [
                'name' => 'collector2',
                'type' => 'type2',
            ],
        ];
        $collector1 = $this->createStub(Collector::class);
        $collector2 = $this->createStub(Collector::class);

        $this->bakusClient->method('request')
            ->willReturn($bakusResponse);
        $this->collectorFactory->method('createFromBakusResponse')
            ->willReturnMap([[$bakusResponse[0], $collector1], [$bakusResponse[1], $collector2]]);

        $collectors = $this->gateway->getCollectors();

        $this->assertCount(2, $collectors);
        $this->assertSame([$collector1, $collector2], $collectors);
    }

    public function testGetCollectorsHandlesInvalidResponseFormat(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to retrieve collectors from Bakus: Invalid response format');

        $this->bakusClient->method('request')
            ->willReturn([
                'not' => 'a list',
            ]);

        $this->gateway->getCollectors();
    }

    public function testGetCollectorsSkipsInvalidCollectorData(): void
    {
        $bakusResponse = [
            [
                'name' => 'collector1',
                'type' => 'type1',
            ],
            [
                'invalid' => 'data',
            ],
        ];
        $collector1 = $this->createStub(Collector::class);

        $this->bakusClient->method('request')
            ->willReturn($bakusResponse);
        $this->collectorFactory->method('createFromBakusResponse')
            ->willReturn($collector1);

        $collectors = $this->gateway->getCollectors();

        // Second item is skipped (missing name/type keys), only first goes to factory
        $this->assertCount(1, $collectors);
        $this->assertSame([$collector1], $collectors);
    }

    public function testGetCollectorsSkipsNotSupportedCollector(): void
    {
        $bakusResponse = [
            [
                'name' => 'collector1',
                'type' => 'type1',
            ],
        ];

        $this->bakusClient->method('request')
            ->willReturn($bakusResponse);
        $this->collectorFactory->method('createFromBakusResponse')
            ->willThrowException(new NotSupportedCollectorException());

        $collectors = $this->gateway->getCollectors();

        $this->assertEmpty($collectors);
    }

    public function testGetCollectorsSkipsInvalidDefinition(): void
    {
        $bakusResponse = [
            [
                'name' => 'collector1',
                'type' => 'type1',
            ],
        ];

        $this->bakusClient->method('request')
            ->willReturn($bakusResponse);
        $this->collectorFactory->method('createFromBakusResponse')
            ->willThrowException(new InvalidCollectorDefinitionException());

        $collectors = $this->gateway->getCollectors();

        $this->assertEmpty($collectors);
    }

    public function testGetTaskStatusSuccess(): void
    {
        $taskId = '123';
        $bakusResponse = [
            'id' => 123,
            'status' => 'in_progress',
            'created_at' => '2024-01-01T10:00:00Z',
        ];

        $this->bakusClient->method('request')
            ->willReturn($bakusResponse);

        $status = $this->gateway->getTaskStatus($taskId);

        $this->assertSame(CollectTaskStatus::RUNNING, $status);
    }

    public function testGetTaskStatusWithDifferentStatuses(): void
    {
        $testCases = [
            ['pending', CollectTaskStatus::QUEUED],
            ['in_progress', CollectTaskStatus::RUNNING],
            ['done', CollectTaskStatus::COMPLETED],
            ['canceled', CollectTaskStatus::CANCELLED],
            ['failed', CollectTaskStatus::FAILED],
        ];

        foreach ($testCases as [$bakusStatus, $expectedStatus]) {
            $this->bakusClient = $this->createStub(BakusHttpClient::class);
            $this->buildGateway();

            $bakusResponse = [
                'id' => 456,
                'status' => $bakusStatus,
            ];

            $this->bakusClient->method('request')
                ->willReturn($bakusResponse);

            $status = $this->gateway->getTaskStatus('456');

            $this->assertSame($expectedStatus, $status);
        }
    }

    public function testGetTaskStatusThrowsExceptionOnInvalidResponse(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to retrieve task status from Bakus: Invalid response format from Bakus');

        $taskId = '123';
        $this->bakusClient->method('request')
            ->willReturn('invalid response');

        $this->gateway->getTaskStatus($taskId);
    }

    public function testGetTaskStatusThrowsExceptionOnMissingStatus(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage(
            'Failed to retrieve task status from Bakus: Missing "status" in response from Bakus'
        );

        $taskId = '123';
        $bakusResponse = [
            'id' => 123,
            'created_at' => '2024-01-01T10:00:00Z',
        ];

        $this->bakusClient->method('request')
            ->willReturn($bakusResponse);

        $this->gateway->getTaskStatus($taskId);
    }

    public function testGetTaskStatusThrowsExceptionOnEmptyStatus(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage(
            'Failed to retrieve task status from Bakus: Empty "status" in response from Bakus'
        );

        $taskId = '123';
        $bakusResponse = [
            'id' => 123,
            'status' => '',
        ];

        $this->bakusClient->method('request')
            ->willReturn($bakusResponse);

        $this->gateway->getTaskStatus($taskId);
    }

    public function testGetTaskStatusThrowsExceptionOnInvalidStatusType(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage(
            'Failed to retrieve task status from Bakus: Invalid "status" type in response from Bakus'
        );

        $taskId = '123';
        $bakusResponse = [
            'id' => 123,
            'status' => 123, // Should be string
        ];

        $this->bakusClient->method('request')
            ->willReturn($bakusResponse);

        $this->gateway->getTaskStatus($taskId);
    }

    public function testGetTaskStatusThrowsExceptionOnHttpError(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to retrieve task status from Bakus: HTTP Error');

        $taskId = '123';
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')
            ->willReturn(Response::HTTP_NOT_FOUND);
        $httpException = new CollectHttpException($response, 'HTTP Error');

        $this->bakusClient->method('request')
            ->willThrowException($httpException);

        $this->gateway->getTaskStatus($taskId);
    }

    public function testGetTaskStatusPreservesHttpExceptionCode(): void
    {
        $taskId = '123';
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')
            ->willReturn(Response::HTTP_NOT_FOUND);
        $httpException = new CollectHttpException($response, 'Not Found', 404);

        $this->bakusClient->method('request')
            ->willThrowException($httpException);

        try {
            $this->gateway->getTaskStatus($taskId);
        } catch (CollectException $e) {
            $this->assertSame(404, $e->getCode());
            $this->assertSame($httpException, $e->getPrevious());
        }
    }

    public function testGetDocumentContentSuccess(): void
    {
        $documentHash = 'abc123def456';
        $resultType = 'pdf';
        $expectedContent = 'PDF document content here';

        $this->bakusClient->method('request')
            ->willReturn($expectedContent);

        $content = $this->gateway->getDocumentContent($documentHash, $resultType);

        $this->assertSame($expectedContent, $content);
    }

    public function testGetDocumentContentWithDifferentResultTypes(): void
    {
        $testCases = [
            ['abc123', 'pdf', 'PDF content'],
            ['def456', 'html', 'HTML content'],
            ['ghi789', 'text', 'Text content'],
            ['jkl012', 'json', 'JSON content'],
        ];

        foreach ($testCases as [$documentHash, $resultType, $expectedContent]) {
            $this->bakusClient = $this->createStub(BakusHttpClient::class);
            $this->buildGateway();

            $this->bakusClient->method('request')
                ->willReturn($expectedContent);

            $content = $this->gateway->getDocumentContent($documentHash, $resultType);

            $this->assertSame($expectedContent, $content);
        }
    }

    public function testGetDocumentContentThrowsExceptionOnInvalidResponseFormat(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to retrieve document from Bakus: Invalid response format');

        $documentHash = 'abc123def456';
        $resultType = 'pdf';

        $this->bakusClient->method('request')
            ->willReturn([
                'not' => 'a string',
            ]);

        $this->gateway->getDocumentContent($documentHash, $resultType);
    }

    public function testGetDocumentContentThrowsExceptionOnNonStringResponse(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to retrieve document from Bakus: Invalid response format');

        $documentHash = 'abc123def456';
        $resultType = 'pdf';

        $this->bakusClient->method('request')
            ->willReturn(12345); // Non-string response

        $this->gateway->getDocumentContent($documentHash, $resultType);
    }

    public function testGetDocumentContentThrowsExceptionOnHttpError(): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to retrieve document from Bakus: HTTP Error');

        $documentHash = 'abc123def456';
        $resultType = 'pdf';

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')
            ->willReturn(Response::HTTP_INTERNAL_SERVER_ERROR);
        $httpException = new CollectHttpException($response, 'HTTP Error');

        $this->bakusClient->method('request')
            ->willThrowException($httpException);

        $this->gateway->getDocumentContent($documentHash, $resultType);
    }

    public function testGetDocumentContentPreservesHttpExceptionCode(): void
    {
        $documentHash = 'abc123def456';
        $resultType = 'pdf';

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')
            ->willReturn(Response::HTTP_NOT_FOUND);
        $httpException = new CollectHttpException($response, 'Not Found', 404);

        $this->bakusClient->method('request')
            ->willThrowException($httpException);

        try {
            $this->gateway->getDocumentContent($documentHash, $resultType);
        } catch (CollectException $e) {
            $this->assertSame(0, $e->getCode()); // getDocumentContent passes 0 as code
            $this->assertSame($httpException, $e->getPrevious());
        }
    }

    public function testGetDocumentContentFallsBackToRefinedOn404WithRawType(): void
    {
        $bakusClientMock = $this->createMock(BakusHttpClient::class);
        $this->bakusClient = $bakusClientMock;
        $this->buildGateway();
        $documentHash = 'abc123def456';
        $expectedContent = 'Refined document content';

        $response404 = $this->createStub(ResponseInterface::class);
        $response404->method('getStatusCode')
            ->willReturn(Response::HTTP_NOT_FOUND);
        $httpException = new CollectHttpException($response404, 'Not Found');

        $bakusClientMock->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $path, $options) use ($httpException, $expectedContent) {
                $resultType = $options['query']['result_type'];
                if ('raw' === $resultType) {
                    throw $httpException;
                }
                if ('refined' === $resultType) {
                    return $expectedContent;
                }

                return '';
            });

        $content = $this->gateway->getDocumentContent($documentHash, 'raw');

        $this->assertSame($expectedContent, $content);
    }

    public function testGetDocumentContentFallbackFailsWhenBothRawAndRefinedReturn404(): void
    {
        $bakusClientMock = $this->createMock(BakusHttpClient::class);
        $this->bakusClient = $bakusClientMock;
        $this->buildGateway();
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to retrieve document from Bakus: Not Found');

        $documentHash = 'abc123def456';

        $response404 = $this->createStub(ResponseInterface::class);
        $response404->method('getStatusCode')
            ->willReturn(Response::HTTP_NOT_FOUND);
        $httpException = new CollectHttpException($response404, 'Not Found');

        $bakusClientMock->expects($this->exactly(2))
            ->method('request')
            ->willThrowException($httpException);

        $this->gateway->getDocumentContent($documentHash, 'raw');
    }

    public function testGetDocumentContentDoesNotFallbackOnNon404Error(): void
    {
        $bakusClientMock = $this->createMock(BakusHttpClient::class);
        $this->bakusClient = $bakusClientMock;
        $this->buildGateway();
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to retrieve document from Bakus: Server Error');

        $documentHash = 'abc123def456';

        $response500 = $this->createStub(ResponseInterface::class);
        $response500->method('getStatusCode')
            ->willReturn(Response::HTTP_INTERNAL_SERVER_ERROR);
        $httpException = new CollectHttpException($response500, 'Server Error');

        // Should only be called once - no fallback for 500 errors
        $bakusClientMock->expects($this->once())
            ->method('request')
            ->willThrowException($httpException);

        $this->gateway->getDocumentContent($documentHash, 'raw');
    }

    public function testGetDocumentContentDoesNotFallbackForNonRawOrRefinedResultType(): void
    {
        $bakusClientMock = $this->createMock(BakusHttpClient::class);
        $this->bakusClient = $bakusClientMock;
        $this->buildGateway();
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Failed to retrieve document from Bakus: Not Found');

        $documentHash = 'abc123def456';

        $response404 = $this->createStub(ResponseInterface::class);
        $response404->method('getStatusCode')
            ->willReturn(Response::HTTP_NOT_FOUND);
        $httpException = new CollectHttpException($response404, 'Not Found');

        // Should only be called once - no fallback when result_type is not 'raw' or 'refined'
        $bakusClientMock->expects($this->once())
            ->method('request')
            ->willThrowException($httpException);

        $this->gateway->getDocumentContent($documentHash, 'pdf');
    }

    public function testGetDocumentContentFallsBackToRawOn404WithRefinedType(): void
    {
        $bakusClientMock = $this->createMock(BakusHttpClient::class);
        $this->bakusClient = $bakusClientMock;
        $this->buildGateway();
        $documentHash = 'abc123def456';
        $expectedContent = 'Raw document content';

        $response404 = $this->createStub(ResponseInterface::class);
        $response404->method('getStatusCode')
            ->willReturn(Response::HTTP_NOT_FOUND);
        $httpException = new CollectHttpException($response404, 'Not Found');

        $bakusClientMock->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $path, $options) use ($httpException, $expectedContent) {
                $resultType = $options['query']['result_type'];
                if ('refined' === $resultType) {
                    throw $httpException;
                }
                if ('raw' === $resultType) {
                    return $expectedContent;
                }

                return '';
            });

        $content = $this->gateway->getDocumentContent($documentHash, 'refined');

        $this->assertSame($expectedContent, $content);
    }
}
