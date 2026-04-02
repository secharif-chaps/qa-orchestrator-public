<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Bakus;

use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\Exception\CollectHttpException;
use App\Domain\Collect\Exception\InvalidCollectorDefinitionException;
use App\Domain\Collect\Exception\NotSupportedCollectorException;
use App\Domain\Collect\ProviderGatewayInterface;
use App\Infrastructure\Collect\Bakus\Client\BakusHttpClient;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

class BakusProviderGateway implements ProviderGatewayInterface
{
    public const string RESULT_TYPE_RAW = 'raw';
    public const string RESULT_TYPE_REFINED = 'refined';

    /**
     * @var array<string, string>
     */
    private array $tasksStatuses = [];

    public function __construct(
        private readonly BakusHttpClient $bakusClient,
        private readonly BakusCollectTaskMapper $mapper,
        private readonly BakusStatusMapper $statusMapper,
        private readonly CollectorFactory $collectorFactory,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    private function setCachedTaskStatus(string $taskId, string $status): void
    {
        $this->tasksStatuses[$taskId] = $status;
    }

    private function getCachedTaskStatus(string $taskId): ?string
    {
        return $this->tasksStatuses[$taskId] ?? null;
    }

    private function clearCachedTaskStatus(string $taskId): void
    {
        unset($this->tasksStatuses[$taskId]);
    }

    public function createTask(CollectTask $collectTask): string
    {
        $query = $this->mapper->mapCollectTaskToBakusQuery($collectTask);

        try {
            $data = $this->bakusClient->request('POST', '/queries', [
                'json' => $query,
            ]);

            if (!isset($data['id'])) {
                $this->logger?->error(
                    'Failed to create task in Bakus: Missing "id" in response',
                    [
                        'collect_task_id' => $collectTask->getId(),
                        'response' => $data,
                    ]
                );

                throw new CollectException(\sprintf(
                    'Failed to create task in Bakus: Missing "id" in response. Response: %s',
                    json_encode($data, \JSON_THROW_ON_ERROR),
                ));
            }

            if (!is_numeric($data['id']) || (int) $data['id'] <= 0) {
                $this->logger?->error(
                    'Failed to create task in Bakus: Invalid "id" in response',
                    [
                        'collect_task_id' => $collectTask->getId(),
                        'response' => $data,
                    ]
                );

                throw new CollectException(\sprintf(
                    'Failed to create task in Bakus: Invalid "id" in response. Response: %s',
                    json_encode($data, \JSON_THROW_ON_ERROR),
                ));
            }

            $this->logger?->info('CollectTask created in Bakus', [
                'collect_task_id' => $collectTask->getId(),
                'provider_task_id' => $data['id'],
            ]);

            if (isset($data['status']) && \is_string($data['status'])) {
                $this->setCachedTaskStatus((string) $data['id'], $data['status']);
            }

            return (string) $data['id'];
        } catch (CollectHttpException $e) {
            $this->logger?->error('Failed to create task in Bakus', [
                'collect_task_id' => $collectTask->getId(),
                'error' => $e->getMessage(),
                'status_code' => $e->response->getStatusCode(),
            ]);

            throw new CollectException('Failed to create task in Bakus: ' . $e->getMessage(), 0, $e);
        }
    }

    public function cancelTask(string $taskId): void
    {
        try {
            $this->bakusClient->request('POST', \sprintf('/queries/%s/cancel', $taskId), [], false);
            $this->logger?->info('Task cancelled in Bakus', [
                'provider_task_id' => $taskId,
            ]);
        } catch (CollectHttpException $e) {
            $this->logger?->error('Failed to cancel task in Bakus', [
                'provider_task_id' => $taskId,
                'error' => $e->getMessage(),
                'status_code' => $e->response->getStatusCode(),
            ]);

            throw new CollectException('Failed to cancel task in Bakus: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getTaskStatus(string $taskId): CollectTaskStatus
    {
        try {
            $status = $this->getCachedTaskStatus($taskId);
            if ($status) {
                $this->clearCachedTaskStatus($taskId);

                return $this->statusMapper->mapStatus($status);
            }

            $data = $this->bakusClient->request('GET', \sprintf('/queries/%s', $taskId));

            Assert::isArray($data, 'Invalid response format from Bakus');
            Assert::keyExists($data, 'status', 'Missing "status" in response from Bakus');
            Assert::notEmpty($data['status'], 'Empty "status" in response from Bakus');
            Assert::string($data['status'], 'Invalid "status" type in response from Bakus');

            $status = $this->statusMapper->mapStatus($data['status']);

            $this->logger?->debug(
                \sprintf('Task %s status retrieved from Bakus: %s', $taskId, $data['status']),
                [
                    'provider_task_id' => $taskId,
                    'collect_task_status' => $status->value,
                    'response' => $data,
                ]
            );

            return $status;
        } catch (InvalidArgumentException $e) {
            $this->logger?->error('Failed to retrieve task status from Bakus: ' . $e->getMessage(), [
                'provider_task_id' => $taskId,
            ]);

            throw new CollectException('Failed to retrieve task status from Bakus: ' . $e->getMessage(), 0, $e);
        } catch (CollectHttpException $e) {
            $this->logger?->error('Failed to retrieve task status from Bakus', [
                'provider_task_id' => $taskId,
                'error' => $e->getMessage(),
                'status_code' => $e->response->getStatusCode(),
            ]);

            throw new CollectException(
                'Failed to retrieve task status from Bakus: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function getCollectors(): array
    {
        $data = $this->bakusClient->request('GET', '/collector_modules/');

        if (!\is_array($data) || !array_is_list($data) || empty($data)) {
            $this->logger?->error('Failed to retrieve collectors from Bakus: Invalid response format', [
                'response' => $data,
            ]);

            throw new CollectException('Failed to retrieve collectors from Bakus: Invalid response format');
        }

        $collectors = [];
        foreach ($data as $collectorData) {
            if (!\is_array($collectorData) || !isset($collectorData['name'], $collectorData['type'])) {
                $this->logger?->warning('Invalid collector data received from Bakus', [
                    'collector_data' => $collectorData,
                ]);

                continue; // Skip invalid collector data
            }

            try {
                $collectors[] = $this->collectorFactory->createFromBakusResponse($collectorData);
            } catch (NotSupportedCollectorException $exception) {
                $this->logger?->debug(
                    \sprintf(
                        '"%s" Bakus Collector is not supported yet. Skipping collector creation',
                        \is_string($collectorData['name']) ? $collectorData['name'] : 'unknown',
                    ),
                    [
                        'collector_data' => $collectorData,
                        'exception' => $exception->getMessage(),
                    ],
                );
                // Continue processing other collectors
            } catch (InvalidCollectorDefinitionException $exception) {
                $this->logger?->warning(
                    \sprintf(
                        'Invalid collector definition for "%s": %s. Skipping collector creation',
                        \is_string($collectorData['name']) ? $collectorData['name'] : 'unknown',
                        $exception->getMessage(),
                    ),
                    [
                        'collector_data' => $collectorData,
                        'exception' => $exception->getMessage(),
                    ],
                );
                // Continue processing other collectors
            }
        }

        $this->logger?->info('Successfully retrieved collectors from Bakus', [
            'collector_count' => \count($collectors),
        ]);

        return $collectors;
    }

    public function getDocumentContent(string $documentHash, string $resultType): string
    {
        try {
            return $this->fetchDocumentContent($documentHash, $resultType);
        } catch (CollectHttpException $e) {
            // Bidirectional fallback on 404: raw <-> refined
            if (404 === $e->response->getStatusCode()) {
                $fallbackType = match ($resultType) {
                    self::RESULT_TYPE_RAW => self::RESULT_TYPE_REFINED,
                    self::RESULT_TYPE_REFINED => self::RESULT_TYPE_RAW,
                    default => null,
                };

                if (null !== $fallbackType) {
                    $this->logger?->info('Document not found, trying fallback type', [
                        'document_hash' => $documentHash,
                        'original_result_type' => $resultType,
                        'fallback_result_type' => $fallbackType,
                    ]);

                    try {
                        return $this->fetchDocumentContent($documentHash, $fallbackType);
                    } catch (CollectHttpException $fallbackException) {
                        $this->logger?->error('Failed to retrieve document from Bakus with fallback', [
                            'document_hash' => $documentHash,
                            'original_result_type' => $resultType,
                            'fallback_result_type' => $fallbackType,
                            'error' => $fallbackException->getMessage(),
                            'status_code' => $fallbackException->response->getStatusCode(),
                        ]);

                        throw new CollectException(
                            'Failed to retrieve document from Bakus: ' . $fallbackException->getMessage(),
                            0,
                            $fallbackException
                        );
                    }
                }
            }

            $this->logger?->error('Failed to retrieve document from Bakus', [
                'document_hash' => $documentHash,
                'error' => $e->getMessage(),
                'status_code' => $e->response->getStatusCode(),
            ]);

            throw new CollectException('Failed to retrieve document from Bakus: ' . $e->getMessage(), 0, $e);
        }
    }

    private function fetchDocumentContent(string $documentHash, string $resultType): string
    {
        $body = [
            'result_type' => $resultType,
            'base64_hash_document_sha1' => $documentHash,
        ];

        $this->logger?->debug('Getting document hash from Bakus', [
            'document_hash' => $documentHash,
            'body' => $body,
        ]);

        $data = $this->bakusClient->request('POST', '/documents/download', [
            'query' => $body,
        ], false);

        if (!\is_string($data)) {
            $this->logger?->error('Failed to retrieve document from Bakus: Invalid response format', [
                'document_hash' => $documentHash,
                'response' => $data,
            ]);

            throw new CollectException('Failed to retrieve document from Bakus: Invalid response format');
        }

        $this->logger?->info('Document content retrieved from Bakus', [
            'document_hash' => $documentHash,
            'result_type' => $resultType,
            'content_length' => \strlen($data),
        ]);

        return $data;
    }
}
