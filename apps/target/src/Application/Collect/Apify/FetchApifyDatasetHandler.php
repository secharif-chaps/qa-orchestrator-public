<?php

declare(strict_types=1);

namespace App\Application\Collect\Apify;

use App\Application\Document\IngestDocumentAction;
use App\Domain\Collect\ApifyClientInterface;
use App\Domain\Collect\ApifyDocumentNormalizerInterface;
use App\Domain\Collect\ApifyNormalizerResolverInterface;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\CollectTaskResult;
use App\Domain\Collect\Exception\ApifyDatasetFetchException;
use App\Domain\Collect\Exception\ApifyDatasetNotFoundException;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\Exception\CollectTaskNotFoundException;
use App\Domain\Collect\NormalizerContext;
use App\Domain\SourceActivity\SourceActivityGatewayInterface;
use App\Domain\SourceActivity\SourceActivityLoggerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

#[AsMessageHandler]
readonly class FetchApifyDatasetHandler
{
    private const int BATCH_SIZE = 100;

    public function __construct(
        private ApifyClientInterface $apifyClient,
        private CollectTaskGatewayInterface $collectTaskGateway,
        private EventDispatcherInterface $eventDispatcher,
        private SourceActivityLoggerInterface $sourceActivityLogger,
        private SourceActivityGatewayInterface $sourceActivityGateway,
        private ApifyNormalizerResolverInterface $normalizerResolver,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(FetchApifyDatasetAction $action): void
    {
        try {
            $collectTask = $this->collectTaskGateway->get($action->collectTaskId);
        } catch (CollectTaskNotFoundException $e) {
            $this->logger->error('Failed to fetch CollectTask for dataset fetch', [
                'collect_task_id' => $action->collectTaskId,
                'dataset_id' => $action->datasetId,
                'error' => $e->getMessage(),
            ]);

            throw new CollectException(\sprintf('CollectTask not found: %s', $action->collectTaskId), 0, $e);
        }

        try {
            $normalizer = $this->normalizerResolver->resolveFor($collectTask);
            ['actorId' => $apifyActorId, 'runId' => $runId] = $this->parseProviderTaskId(
                $collectTask->getProviderTaskId()
            );

            $datasetMetadata = $this->fetchDatasetMetadata($action->datasetId);
            $itemCount = $datasetMetadata['itemCount'] ?? 0;
            if (!\is_int($itemCount)) {
                $itemCount = 0;
            }

            $this->logger->info('Starting Apify dataset fetch', [
                'collect_task_id' => $action->collectTaskId,
                'dataset_id' => $action->datasetId,
                'item_count' => $itemCount,
                'provider_task_id' => $collectTask->getProviderTaskId(),
                'provider_name' => 'apify',
            ]);

            $stats = $this->processDatasetItems($action->collectTaskId, $action->datasetId, $normalizer, $apifyActorId);

            $result = CollectTaskResult::success(
                [],
                [
                    'itemCount' => $itemCount,
                    'documentsCreated' => $stats['created'],
                    'normalizationErrors' => $stats['errors'],
                    'fetchedAt' => new \DateTimeImmutable()
                        ->format('Y-m-d H:i:s'),
                    'datasetId' => $action->datasetId,
                ]
            );

            $collectTask->storeResult($result->toArray());

            $oldStatus = $collectTask->getStatus();
            $collectTask->complete($this->eventDispatcher);
            $this->collectTaskGateway->save($collectTask);

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
                    'item_count' => $itemCount,
                    'documents_created' => $stats['created'],
                ]
            );

            if (null !== $action->runCost) {
                $costActivity = $this->sourceActivityLogger->logSourceCollectCost(
                    $source,
                    'apify',
                    $action->runCost,
                    [
                        'apify_actor_id' => $apifyActorId,
                        'run_id' => $runId,
                        'items_collected' => $stats['created'],
                        'collect_task_id' => $collectTask->getId(),
                        'dataset_id' => $action->datasetId,
                    ]
                );
                $this->sourceActivityGateway->save($costActivity);

                $this->logger->info('Apify run cost logged', [
                    'collect_task_id' => $action->collectTaskId,
                    'dataset_id' => $action->datasetId,
                    'apify_actor_id' => $apifyActorId,
                    'run_id' => $runId,
                    'compute_units' => $action->runCost->computeUnits,
                    'cost_usd' => $action->runCost->costUsd,
                    'provider_name' => 'apify',
                ]);
            }

            $this->logger->info('Apify dataset fetch completed', [
                'collect_task_id' => $action->collectTaskId,
                'dataset_id' => $action->datasetId,
                'item_count' => $itemCount,
                'documents_created' => $stats['created'],
                'normalization_errors' => $stats['errors'],
                'provider_name' => 'apify',
            ]);
        } catch (CollectException $e) {
            $this->handleFetchFailure($collectTask, $action, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Fetch all dataset items in batches, normalize each item, dispatch IngestDocumentAction.
     *
     * @return array{created: int, errors: int}
     */
    private function processDatasetItems(
        string $collectTaskId,
        string $datasetId,
        ApifyDocumentNormalizerInterface $normalizer,
        ?string $actorType,
    ): array {
        $documentsCreated = 0;
        $normalizationErrors = 0;
        $offset = 0;

        do {
            try {
                /** @var array<mixed> $batch */
                $batch = $this->apifyClient->request(
                    'GET',
                    \sprintf('/v2/datasets/%s/items', $datasetId),
                    [
                        'query' => [
                            'limit' => (string) self::BATCH_SIZE,
                            'offset' => (string) $offset,
                            'format' => 'json',
                            'clean' => '1',
                        ],
                    ]
                );

                Assert::isList($batch, 'Invalid response format from Apify: expected a list of items');
                Assert::allIsArray($batch, 'Invalid response format from Apify: each item must be an array');

                foreach ($batch as $batchIndex => $item) {
                    try {
                        $context = new NormalizerContext($actorType, $datasetId, $offset + $batchIndex);
                        $document = $normalizer->normalize($item, $context);
                        if (null === $document) {
                            continue;
                        }

                        $this->messageBus->dispatch(new IngestDocumentAction($collectTaskId, $document));
                        ++$documentsCreated;
                    } catch (\Throwable $e) {
                        ++$normalizationErrors;
                        $this->logger->warning('Failed to normalize Apify item', [
                            'collect_task_id' => $collectTaskId,
                            'dataset_id' => $datasetId,
                            'offset' => $offset,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $this->logger->debug('Processed Apify dataset batch', [
                    'dataset_id' => $datasetId,
                    'offset' => $offset,
                    'batch_size' => \count($batch),
                    'documents_created_so_far' => $documentsCreated,
                ]);

                $offset += self::BATCH_SIZE;
            } catch (InvalidArgumentException|CollectException $e) {
                $this->logger->error('Failed to fetch dataset items from Apify', [
                    'dataset_id' => $datasetId,
                    'offset' => $offset,
                    'error' => $e->getMessage(),
                ]);

                throw ApifyDatasetFetchException::forItems($datasetId, $offset, $e->getMessage());
            }
        } while (self::BATCH_SIZE === \count($batch));

        return [
            'created' => $documentsCreated,
            'errors' => $normalizationErrors,
        ];
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
            throw new CollectException('Failed to fetch dataset metadata: ' . $e->getMessage(), 0, $e);
        } catch (CollectException $e) {
            $this->logger->error('Failed to fetch dataset metadata from Apify', [
                'dataset_id' => $datasetId,
                'error' => $e->getMessage(),
            ]);

            throw ApifyDatasetNotFoundException::withId($datasetId);
        }
    }

    private function handleFetchFailure(
        CollectTask $collectTask,
        FetchApifyDatasetAction $action,
        string $errorMessage,
    ): void {
        try {
            $result = CollectTaskResult::failure($errorMessage, [
                'datasetId' => $action->datasetId,
                'failedAt' => new \DateTimeImmutable()
->format('Y-m-d H:i:s'),
            ]);

            $collectTask->storeResult($result->toArray());

            $oldStatus = $collectTask->getStatus();
            $collectTask->fail($this->eventDispatcher);
            $this->collectTaskGateway->save($collectTask);

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

            $this->logger->error('Apify dataset fetch failed', [
                'collect_task_id' => $action->collectTaskId,
                'dataset_id' => $action->datasetId,
                'error' => $errorMessage,
                'provider_name' => 'apify',
            ]);
        } catch (CollectException $e) {
            $this->logger->critical('Failed to handle dataset fetch failure', [
                'collect_task_id' => $action->collectTaskId,
                'dataset_id' => $action->datasetId,
                'original_error' => $errorMessage,
                'handling_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Parses a providerTaskId formatted as "{apifyActorId}:{runId}" into its components.
     *
     * @return array{actorId: ?string, runId: ?string}
     */
    private function parseProviderTaskId(?string $providerTaskId): array
    {
        if (null === $providerTaskId || '' === $providerTaskId) {
            return [
                'actorId' => null,
                'runId' => null,
            ];
        }

        $parts = explode(':', $providerTaskId, 2);

        return [
            'actorId' => '' !== $parts[0] ? $parts[0] : null,
            'runId' => isset($parts[1]) && '' !== $parts[1] ? $parts[1] : null,
        ];
    }
}
