<?php

declare(strict_types=1);

namespace App\Application\Collect;

use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\ProviderGatewayInterface;
use App\Domain\Collect\Stream\CollectDataPullStreamInterface;
use App\Domain\Collect\Stream\Exception\CollectDataStreamException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles pull stream connections for collect task data.
 *
 * This handler initiates an outbound WebSocket connection (client mode) to the provider's
 * WebSocket server to pull real-time streaming data for a specific collect task.
 */
#[AsMessageHandler]
readonly class PullStreamCollectTaskDataHandler
{
    public function __construct(
        private CollectTaskGatewayInterface $collectTaskGateway,
        private ProviderGatewayInterface $providerGateway,
        private CollectDataPullStreamInterface $pullStream,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(PullStreamCollectTaskDataAction $action): bool
    {
        $collectTask = $this->collectTaskGateway->get($action->collectTaskId);

        if (!$collectTask->getStatus()->isActive()) {
            throw new CollectDataStreamException(\sprintf(
                'Collect task %s is not active (status: %s)',
                $action->collectTaskId,
                $collectTask->getStatus()->value,
            ));
        }

        $providerTaskId = $collectTask->getProviderTaskId();
        if (!$providerTaskId) {
            throw new CollectDataStreamException(\sprintf(
                'Collect task %s has no provider task ID',
                $action->collectTaskId,
            ));
        }

        $taskStatus = $this->providerGateway->getTaskStatus($providerTaskId);
        if (!$taskStatus->isActive()) {
            throw new CollectDataStreamException(\sprintf(
                'Provider task %s is not active (status: %s)',
                $providerTaskId,
                $taskStatus->value,
            ));
        }

        $this->logger?->info('Starting WebSocket connection', [
            'collect_task_id' => $action->collectTaskId,
            'provider_task_id' => $providerTaskId,
            'provider_task_status' => $taskStatus->value,
        ]);

        try {
            return $this->pullStream->connect($action->collectTaskId, $providerTaskId);
        } catch (CollectDataStreamException $e) {
            $this->logger?->error('WebSocket connection error', [
                'collect_task_id' => $action->collectTaskId,
                'provider_task_id' => $providerTaskId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            $this->logger?->error('WebSocket connection failed', [
                'collect_task_id' => $action->collectTaskId,
                'provider_task_id' => $providerTaskId,
                'error' => $e->getMessage(),
            ]);

            throw new CollectDataStreamException($e->getMessage(), 0, $e);
        } finally {
            $this->pullStream->disconnect();
        }
    }
}
