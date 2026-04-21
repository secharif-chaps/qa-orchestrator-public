<?php

declare(strict_types=1);

namespace App\Application\Collect\Apify;

use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\CollectTaskResult;
use App\Domain\Collect\Exception\CollectException;
use App\Infrastructure\Collect\Apify\Client\ApifyHttpClient;
use App\Infrastructure\SourceActivity\SourceActivityLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

#[AsMessageHandler]
readonly class FetchApifyDatasetHandler
{
    private const int BATCH_SIZE = 100;

    public function __construct(
        private ApifyHttpClient $apifyClient,
        private CollectTaskGatewayInterface $collectTaskGateway,
        private EventDispatcherInterface $eventDispatcher,
        private SourceActivityLogger $sourceActivityLogger,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(FetchApifyDatasetAction $action): void
    {
        try {
            $collectTask = $this->collectTaskGateway->get($action->collectTaskId);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to fetch CollectTask for dataset fetch', [
                'collect_task_id' => $action->collectTaskId,
                'dataset_id' => $action->datasetId,
                'error' => $e->getMessage(),
            ]);

            throw new CollectException(\sprintf('CollectTask not found: %s', $action->collectTaskId), 0, $e);
        }

        try {
            // Fetch dataset metadata to get item count
            $datasetMetadata = $this->fetchDatasetMetadata($action->datasetId);
            $itemCount = $datasetMetadata['itemCount'] ?? 0;
            if (!\is_int($itemCount)) {
                $itemCount = 0;
            }

            $this->logger?->info('Starting Apify dataset fetch', [
                'collect_task_id' => $action->collectTaskId,
                'dataset_id' => $action->datasetId,
                'item_count' => $itemCount,
                'provider_name' => 'apify',
            ]);

            // Fetch all dataset items with pagination
            $allItems = $this->fetchAllDatasetItems($action->datasetId, $itemCount);

            // Create result with fetched items and metadata
            /** @var array<string, mixed> $allItemsArray */
            $allItemsArray = $allItems;
            $result = CollectTaskResult::success(
                $allItemsArray,
                [
                    'itemCount' => $itemCount,
                    'fetchedAt' => new \DateTimeImmutable()
->format('Y-m-d H:i:s'),
                    'datasetId' => $action->datasetId,
                ]
            );

            // Store result in CollectTask configuration
            $collectTask->storeResult($result->toArray());

            // Get old status before marking complete
            $oldStatus = $collectTask->getStatus();

            // Mark as completed
            $collectTask->complete($this->eventDispatcher);

            // Save updated task
            $this->collectTaskGateway->save($collectTask);

            // Log source activity
            $source = $collectTask->getSource();
            $this->sourceActivityLogger->logSourceCollectStatusChanged(
                $source,
                null,
                $oldStatus,
                $collectTask->getStatus(),
                [
                    'provider_name' => 'apify',
                    'collect_task_id' => $collectTask->getId(),
                    'dataset_id' => $action->datasetId,
                    'item_count' => \count($allItems),
                ]
            );

            $this->logger?->info('Apify dataset fetch completed successfully', [
                'collect_task_id' => $action->collectTaskId,
                'dataset_id' => $action->datasetId,
                'item_count' => \count($allItems),
                'provider_name' => 'apify',
            ]);
        } catch (CollectException $e) {
            $this->handleFetchFailure($collectTask, $action, $e->getMessage());

            throw $e;
        } catch (\Throwable $e) {
            $this->handleFetchFailure($collectTask, $action, \sprintf('%s: %s', $e::class, $e->getMessage()));

            throw new CollectException(\sprintf(
                'Failed to fetch Apify dataset %s: %s',
                $action->datasetId,
                $e->getMessage()
            ), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchDatasetMetadata(string $datasetId): array
    {
        try {
            /** @var array<string, mixed> $data */
            $data = $this->apifyClient->request('GET', \sprintf('/v2/datasets/%s', $datasetId));

            Assert::isArray($data, 'Invalid response format from Apify');
            Assert::keyExists($data, 'data', 'Missing "data" in response from Apify');
            Assert::isArray($data['data'], 'Invalid "data" format in response from Apify');

            return $data['data'];
        } catch (InvalidArgumentException $e) {
            $this->logger?->error('Failed to fetch dataset metadata from Apify: ' . $e->getMessage(), [
                'dataset_id' => $datasetId,
            ]);

            throw new CollectException('Failed to fetch dataset metadata: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to fetch dataset metadata from Apify', [
                'dataset_id' => $datasetId,
                'error' => $e->getMessage(),
            ]);

            throw new CollectException(\sprintf('Failed to fetch dataset metadata: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllDatasetItems(string $datasetId, int $itemCount): array
    {
        $allItems = [];
        $offset = 0;

        while ($offset < $itemCount) {
            try {
                /** @var array<string, mixed> $response */
                $response = $this->apifyClient->request(
                    'GET',
                    \sprintf('/v2/datasets/%s/items', $datasetId),
                    [
                        'query' => [
                            'limit' => (string) self::BATCH_SIZE,
                            'offset' => (string) $offset,
                        ],
                    ]
                );

                Assert::isArray($response, 'Invalid response format from Apify');

                /** @var list<array<string, mixed>> $items */
                $items = $response;
                $allItems = [...$allItems, ...$items];

                $offset += self::BATCH_SIZE;

                $this->logger?->debug('Fetched Apify dataset batch', [
                    'dataset_id' => $datasetId,
                    'offset' => $offset - self::BATCH_SIZE,
                    'batch_size' => \count($items),
                ]);
            } catch (\Throwable $e) {
                $this->logger?->error('Failed to fetch dataset items from Apify', [
                    'dataset_id' => $datasetId,
                    'offset' => $offset,
                    'error' => $e->getMessage(),
                ]);

                throw new CollectException(\sprintf(
                    'Failed to fetch dataset items (offset %d): %s',
                    $offset,
                    $e->getMessage()
                ), 0, $e);
            }
        }

        return $allItems;
    }

    private function handleFetchFailure(
        CollectTask $collectTask,
        FetchApifyDatasetAction $action,
        string $errorMessage,
    ): void {
        try {
            // Create failure result
            $result = CollectTaskResult::failure($errorMessage, [
                'datasetId' => $action->datasetId,
                'failedAt' => new \DateTimeImmutable()
->format('Y-m-d H:i:s'),
            ]);

            // Store failure result in configuration
            $collectTask->storeResult($result->toArray());

            // Get old status before marking failed
            $oldStatus = $collectTask->getStatus();

            // Mark as failed
            $collectTask->fail($this->eventDispatcher);

            // Save updated task
            $this->collectTaskGateway->save($collectTask);

            // Log source activity
            $source = $collectTask->getSource();
            $this->sourceActivityLogger->logSourceCollectStatusChanged(
                $source,
                null,
                $oldStatus,
                $collectTask->getStatus(),
                [
                    'provider_name' => 'apify',
                    'collect_task_id' => $collectTask->getId(),
                    'dataset_id' => $action->datasetId,
                    'error' => $errorMessage,
                ]
            );

            $this->logger?->error('Apify dataset fetch failed', [
                'collect_task_id' => $action->collectTaskId,
                'dataset_id' => $action->datasetId,
                'error' => $errorMessage,
                'provider_name' => 'apify',
            ]);
        } catch (\Throwable $e) {
            $this->logger?->critical('Failed to handle dataset fetch failure', [
                'collect_task_id' => $action->collectTaskId,
                'dataset_id' => $action->datasetId,
                'original_error' => $errorMessage,
                'handling_error' => $e->getMessage(),
            ]);
        }
    }
}
