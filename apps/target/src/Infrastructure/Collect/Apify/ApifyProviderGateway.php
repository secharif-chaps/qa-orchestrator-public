<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify;

use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\Exception\CollectHttpException;
use App\Domain\Collect\ProviderGatewayInterface;
use App\Domain\Collect\ValueObject\Collector;
use App\Domain\Source\SourceType;
use App\Infrastructure\Collect\Apify\Client\ApifyHttpClient;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

class ApifyProviderGateway implements ProviderGatewayInterface
{
    /**
     * @var array<string, string>
     */
    private array $tasksStatuses = [];

    public function __construct(
        private readonly ApifyHttpClient $apifyClient,
        private readonly ApifyCollectTaskMapper $mapper,
        private readonly ApifyStatusMapper $statusMapper,
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
        $config = $this->mapper->mapToActorRun($collectTask);

        try {
            /** @var array<string, mixed> $data */
            $data = $this->apifyClient->request(
                'POST',
                \sprintf('/v2/acts/%s/runs', $this->encodeActorId($config->apifyActorId)),
                [
                    'json' => $config->input,
                    'query' => $config->queryParams,
                ],
            );

            $runData = $data['data'] ?? null;
            if (!\is_array($runData) || !isset($runData['id']) || (!\is_string($runData['id']) && !\is_int(
                $runData['id']
            ))) {
                $this->logger?->error(
                    'Failed to create task in Apify: Missing "data.id" in response',
                    [
                        'collect_task_id' => $collectTask->getId(),
                        'apify_actor_id' => $config->apifyActorId,
                        'response' => $data,
                    ],
                );

                throw new CollectException(\sprintf(
                    'Failed to create task in Apify: Missing "data.id" in response. Response: %s',
                    json_encode($data, \JSON_THROW_ON_ERROR),
                ));
            }

            $runId = (string) $runData['id'];
            $ref = new ApifyRunReference($config->apifyActorId, $runId);
            $providerTaskId = $ref->toProviderTaskId();

            $this->logger?->info('CollectTask created in Apify', [
                'collect_task_id' => $collectTask->getId(),
                'provider_task_id' => $providerTaskId,
                'apify_actor_id' => $config->apifyActorId,
                'run_id' => $runId,
            ]);

            if (isset($runData['status']) && \is_string($runData['status'])) {
                $this->setCachedTaskStatus($providerTaskId, $runData['status']);
            }

            return $providerTaskId;
        } catch (CollectHttpException $e) {
            $this->logger?->error('Failed to create task in Apify', [
                'collect_task_id' => $collectTask->getId(),
                'apify_actor_id' => $config->apifyActorId,
                'error' => $e->getMessage(),
                'status_code' => $e->response->getStatusCode(),
            ]);

            throw new CollectException('Failed to create task in Apify: ' . $e->getMessage(), 0, $e);
        }
    }

    public function cancelTask(string $taskId): void
    {
        try {
            $ref = ApifyRunReference::fromProviderTaskId($taskId);

            $this->apifyClient->request(
                'POST',
                $ref->getRunPath() . '/abort',
                [
                    'query' => [
                        'gracefully' => 'true',
                    ],
                ],
                false,
            );

            $this->logger?->info('Task cancelled in Apify', [
                'provider_task_id' => $taskId,
                'apify_actor_id' => $ref->apifyActorId,
                'run_id' => $ref->runId,
            ]);
        } catch (CollectHttpException $e) {
            $this->logger?->error('Failed to cancel task in Apify', [
                'provider_task_id' => $taskId,
                'error' => $e->getMessage(),
                'status_code' => $e->response->getStatusCode(),
            ]);

            throw new CollectException('Failed to cancel task in Apify: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getTaskStatus(string $taskId): CollectTaskStatus
    {
        try {
            $status = $this->getCachedTaskStatus($taskId);
            if (null !== $status) {
                $this->clearCachedTaskStatus($taskId);

                return $this->statusMapper->mapStatus($status);
            }

            $ref = ApifyRunReference::fromProviderTaskId($taskId);

            /** @var array<string, mixed> $data */
            $data = $this->apifyClient->request('GET', $ref->getRunPath());

            Assert::isArray($data, 'Invalid response format from Apify');
            Assert::keyExists($data, 'data', 'Missing "data" in response from Apify');
            Assert::isArray($data['data'], 'Invalid "data" format in response from Apify');
            Assert::keyExists($data['data'], 'status', 'Missing "data.status" in response from Apify');
            Assert::notEmpty($data['data']['status'], 'Empty "data.status" in response from Apify');
            Assert::string($data['data']['status'], 'Invalid "data.status" type in response from Apify');

            $mappedStatus = $this->statusMapper->mapStatus($data['data']['status']);

            $this->logger?->debug(
                \sprintf('Task %s status retrieved from Apify: %s', $taskId, $data['data']['status']),
                [
                    'provider_task_id' => $taskId,
                    'collect_task_status' => $mappedStatus->value,
                    'response' => $data,
                ],
            );

            return $mappedStatus;
        } catch (InvalidArgumentException $e) {
            $this->logger?->error('Failed to retrieve task status from Apify: ' . $e->getMessage(), [
                'provider_task_id' => $taskId,
            ]);

            throw new CollectException('Failed to retrieve task status from Apify: ' . $e->getMessage(), 0, $e);
        } catch (CollectHttpException $e) {
            $this->logger?->error('Failed to retrieve task status from Apify', [
                'provider_task_id' => $taskId,
                'error' => $e->getMessage(),
                'status_code' => $e->response->getStatusCode(),
            ]);

            throw new CollectException(
                'Failed to retrieve task status from Apify: ' . $e->getMessage(),
                $e->getCode(),
                $e,
            );
        }
    }

    /**
     * Returns a static list of collectors from the YAML configuration.
     *
     * Unlike Bakus which discovers collectors via API, Apify actors
     * are statically configured in the service YAML.
     *
     * @return list<Collector>
     */
    public function getCollectors(): array
    {
        $actorMapping = $this->mapper->getActorMapping();
        $collectors = [];

        foreach ($actorMapping as $sourceTypeValue => $apifyActorId) {
            try {
                $sourceType = SourceType::from($sourceTypeValue);
            } catch (\ValueError $e) {
                $this->logger?->warning('Invalid source type in Apify actor mapping', [
                    'source_type' => $sourceTypeValue,
                    'apify_actor_id' => $apifyActorId,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $collectors[] = new Collector(
                name: $apifyActorId,
                displayName: [
                    'en' => $apifyActorId,
                ],
                description: [
                    'en' => \sprintf('Apify actor: %s', $apifyActorId),
                ],
                type: 'apify',
                version: '1.0.0',
                iconUrl: '',
                parameters: [],
                returnTypes: [],
                supportStream: false,
                supportBatch: true,
                supportedSourceTypes: [$sourceType],
            );
        }

        $this->logger?->info('Successfully retrieved collectors from Apify config', [
            'collector_count' => \count($collectors),
        ]);

        return $collectors;
    }

    /**
     * Encodes an Apify actor ID for use in REST API URL paths.
     *
     * The human-readable actor ID format uses "/" as owner/name separator
     * (e.g. "apidojo/tweet-scraper"), but Apify's REST API requires "~"
     * in URL paths (e.g. "apidojo~tweet-scraper").
     */
    private function encodeActorId(string $actorId): string
    {
        return str_replace('/', '~', $actorId);
    }
}
