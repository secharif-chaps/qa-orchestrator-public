<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect\Apify;

use App\Application\Collect\Apify\FetchApifyDatasetAction;
use App\Application\Collect\Apify\FetchApifyDatasetHandler;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Collect\Apify\Client\ApifyHttpClient;
use App\Infrastructure\SourceActivity\SourceActivityLogger;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

#[CoversClass(FetchApifyDatasetHandler::class)]
class FetchApifyDatasetHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private Stub $apifyClient;
    private Stub $collectTaskGateway;
    private Stub $eventDispatcher;
    private Stub $sourceActivityLogger;
    private Stub $logger;
    private FetchApifyDatasetHandler $handler;

    protected function setUp(): void
    {
        $this->apifyClient = $this->createStub(ApifyHttpClient::class);
        $this->collectTaskGateway = $this->createStub(CollectTaskGatewayInterface::class);
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->sourceActivityLogger = $this->createStub(SourceActivityLogger::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->handler = new FetchApifyDatasetHandler(
            $this->apifyClient,
            $this->collectTaskGateway,
            $this->eventDispatcher,
            $this->sourceActivityLogger,
            $this->logger
        );
    }

    public function testSuccessfullyFetchesDatasetItems(): void
    {
        $collectTaskId = Uuid::v4()->toRfc4122();
        $datasetId = 'dataset_123';
        $action = new FetchApifyDatasetAction($collectTaskId, $datasetId);

        // Create test data
        $collectTask = $this->createCollectTask($collectTaskId);
        $testItems = [
            [
                'id' => 'item1',
                'name' => 'Item 1',
            ],
            [
                'id' => 'item2',
                'name' => 'Item 2',
            ],
            [
                'id' => 'item3',
                'name' => 'Item 3',
            ],
        ];

        // Setup mocks
        $this->collectTaskGateway->method('get')
            ->with($collectTaskId)
            ->willReturn($collectTask);

        $this->apifyClient->method('request')
            ->willReturnCallback(function ($method, $path) use ($datasetId, $testItems) {
                if (str_contains($path, '/datasets/' . $datasetId) && !str_contains($path, '/items')) {
                    // Dataset metadata request
                    return [
                        'data' => [
                            'itemCount' => \count($testItems),
                            'id' => $datasetId,
                        ],
                    ];
                }

                // Items request
                return $testItems;
            });

        // Execute
        ($this->handler)($action);

        // Verify
        $this->assertEquals(CollectTaskStatus::COMPLETED, $collectTask->getStatus());

        $configuration = $collectTask->getConfiguration();
        $this->assertArrayHasKey('result', $configuration);
        /** @var array<string, mixed> $result */
        $result = $configuration['result'];
        $this->assertTrue($result['success']);
        /** @var list<array<string, mixed>> $data */
        $data = $result['data'];
        $this->assertCount(3, $data);
        $this->assertEquals('item1', $data[0]['id']);
    }

    public function testFetchesLargeDatasetWithPagination(): void
    {
        $collectTaskId = Uuid::v4()->toRfc4122();
        $datasetId = 'dataset_large';
        $action = new FetchApifyDatasetAction($collectTaskId, $datasetId);

        $collectTask = $this->createCollectTask($collectTaskId);

        // Create 250 test items (requires pagination with batch size 100)
        $testItems = [];
        for ($i = 1; $i <= 250; ++$i) {
            $testItems[] = [
                'id' => 'item' . $i,
                'name' => 'Item ' . $i,
            ];
        }

        // Setup mocks
        $this->collectTaskGateway->method('get')
            ->with($collectTaskId)
            ->willReturn($collectTask);

        $this->apifyClient->method('request')
            ->willReturnCallback(function ($method, $path, $options = []) use ($datasetId, $testItems) {
                if (str_contains($path, '/datasets/' . $datasetId) && !str_contains($path, '/items')) {
                    return [
                        'data' => [
                            'itemCount' => \count($testItems),
                            'id' => $datasetId,
                        ],
                    ];
                }

                // Extract offset from query params for pagination
                $offset = (int) ($options['query']['offset'] ?? 0);
                $limit = (int) ($options['query']['limit'] ?? 100);

                return \array_slice($testItems, $offset, $limit);
            });

        // Execute
        ($this->handler)($action);

        // Verify all items were fetched
        $configuration = $collectTask->getConfiguration();
        /** @var array<string, mixed> $result */
        $result = $configuration['result'];
        $this->assertTrue($result['success']);
        /** @var list<array<string, mixed>> $data */
        $data = $result['data'];
        $this->assertCount(250, $data);
        $this->assertEquals('item1', $data[0]['id']);
        $this->assertEquals('item250', $data[249]['id']);
    }

    public function testFailsWhenDatasetMetadataNotFound(): void
    {
        $collectTaskId = Uuid::v4()->toRfc4122();
        $datasetId = 'dataset_not_found';
        $action = new FetchApifyDatasetAction($collectTaskId, $datasetId);

        $collectTask = $this->createCollectTask($collectTaskId);

        // Setup mocks
        $this->collectTaskGateway->method('get')
            ->willReturn($collectTask);

        $this->apifyClient->method('request')
            ->willThrowException(new \RuntimeException('Dataset not found'));

        // Execute and verify exception
        $this->expectException(CollectException::class);
        $this->expectExceptionMessageMatches('/Failed to fetch/');

        ($this->handler)($action);

        // Verify task was marked as failed
        $this->assertEquals(CollectTaskStatus::FAILED, $collectTask->getStatus());

        $configuration = $collectTask->getConfiguration();
        $this->assertArrayHasKey('result', $configuration);
        /** @var array<string, mixed> $result */
        $result = $configuration['result'];
        $this->assertFalse($result['success']);
    }

    public function testStoresResultMetadata(): void
    {
        $collectTaskId = Uuid::v4()->toRfc4122();
        $datasetId = 'dataset_meta';
        $action = new FetchApifyDatasetAction($collectTaskId, $datasetId);

        $collectTask = $this->createCollectTask($collectTaskId);
        $testItems = [
            [
                'id' => 'item1',
                'data' => 'test',
            ],
        ];

        $this->collectTaskGateway->method('get')
            ->willReturn($collectTask);

        $this->apifyClient->method('request')
            ->willReturnCallback(function ($method, $path) use ($datasetId, $testItems) {
                if (str_contains($path, '/datasets/' . $datasetId) && !str_contains($path, '/items')) {
                    return [
                        'data' => [
                            'itemCount' => \count($testItems),
                            'id' => $datasetId,
                        ],
                    ];
                }

                return $testItems;
            });

        // Execute
        ($this->handler)($action);

        // Verify metadata is stored
        $configuration = $collectTask->getConfiguration();
        /** @var array<string, mixed> $result */
        $result = $configuration['result'];
        $this->assertArrayHasKey('metadata', $result);
        /** @var array<string, mixed> $metadata */
        $metadata = $result['metadata'];
        $this->assertEquals(1, $metadata['itemCount']);
        $this->assertEquals($datasetId, $metadata['datasetId']);
        $this->assertArrayHasKey('fetchedAt', $metadata);
    }

    public function testLogsCollectTaskNotFound(): void
    {
        $collectTaskId = 'unknown-id';
        $action = new FetchApifyDatasetAction($collectTaskId, 'dataset_123');

        $this->collectTaskGateway->method('get')
            ->willThrowException(new \RuntimeException('CollectTask not found'));

        // Execute and verify exception
        $this->expectException(CollectException::class);

        ($this->handler)($action);
    }

    private function createCollectTask(?string $taskId = null): CollectTask
    {
        $source = $this->createStub(Source::class);
        $source->method('getType')
            ->willReturn(SourceType::WEBSITE);
        $source->method('getId')
            ->willReturn(Uuid::v4()->toRfc4122());

        $watchFile = $this->createStub(WatchFile::class);
        $watchFile->method('getId')
            ->willReturn(Uuid::v4()->toRfc4122());

        $task = new CollectTask(
            source: $source,
            watchFile: $watchFile,
            providerName: 'apify',
            configuration: [],
            providerTaskId: 'apify_run_id'
        );

        // Set ID if provided
        if (null !== $taskId) {
            $this->forcePropertyValue($task, $taskId, 'id');
        }

        // Transition to RUNNING state (the webhook callback assumes task is already running)
        // CREATED -> QUEUED -> RUNNING
        /* @phpstan-ignore-next-line */
        $task->start('apify_run_id', $this->eventDispatcher);
        $this->forcePropertyValue($task, CollectTaskStatus::RUNNING, 'status');

        return $task;
    }
}
