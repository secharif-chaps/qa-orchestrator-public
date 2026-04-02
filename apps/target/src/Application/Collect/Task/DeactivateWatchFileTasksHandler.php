<?php

declare(strict_types=1);

namespace App\Application\Collect\Task;

use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\Exception\CollectHttpException;
use App\Domain\Collect\ProviderGatewayInterface;
use App\Domain\Source\SourceGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class DeactivateWatchFileTasksHandler
{
    public function __construct(
        private ProviderGatewayInterface $providerGateway,
        private CollectTaskGatewayInterface $collectTaskGateway,
        private SourceGatewayInterface $sourceGateway,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(DeactivateWatchFileTasksAction $action): void
    {
        $activeTasks = $this->collectTaskGateway->findActiveTasksByWatchFileId($action->watchFileId);

        $this->logger?->info('Deactivating CollectTasks for WatchFile', [
            'watchFileId' => $action->watchFileId,
            'taskCount' => \count($activeTasks),
        ]);

        foreach ($activeTasks as $task) {
            if (!$task->getStatus()->canTransitionTo(CollectTaskStatus::CANCELLED)) {
                $this->logger?->warning(
                    'Cannot cancel task that is not in a cancellable state',
                    [
                        'task_id' => $task->getId(),
                        'status' => $task->getStatus()
                            ->value,
                    ],
                );

                continue;
            }

            $providerTaskId = $task->getProviderTaskId();
            if (null === $providerTaskId) {
                $this->logger?->warning(
                    'Cannot cancel task without provider task ID',
                    [
                        'task_id' => $task->getId(),
                    ],
                );

                continue;
            }

            $this->cancelTask($task, $providerTaskId);
            $source = $task->getSource();
            $source->updateCollectStatus($task);
            $this->sourceGateway->save($source);
        }
    }

    private function cancelTask(CollectTask $task, string $providerTaskId): void
    {
        try {
            $this->providerGateway->cancelTask($providerTaskId);
            $task->cancel();
            $this->collectTaskGateway->save($task, false);

            $this->logger?->info('Task cancelled successfully', [
                'task_id' => $task->getId(),
                'provider_task_id' => $providerTaskId,
            ]);
        } catch (CollectHttpException $e) {
            // Handle 404: task already deleted on Bakus side (idempotent)
            if (404 === $e->response->getStatusCode()) {
                $this->logger?->info('Task already deleted on provider side, cleaning up local mapping', [
                    'task_id' => $task->getId(),
                    'provider_task_id' => $providerTaskId,
                ]);

                $task->cancel();
                $this->collectTaskGateway->save($task, false);

                return;
            }

            // Log error but continue with other tasks (no retry to avoid blocking)
            $this->logger?->error('Failed to cancel task on provider', [
                'task_id' => $task->getId(),
                'provider_task_id' => $providerTaskId,
                'error' => $e->getMessage(),
                'status_code' => $e->response->getStatusCode(),
            ]);
        } catch (CollectException $e) {
            // Log non-HTTP errors and continue with other tasks
            $this->logger?->error('Failed to cancel task', [
                'task_id' => $task->getId(),
                'provider_task_id' => $providerTaskId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
