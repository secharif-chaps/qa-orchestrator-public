<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect;

use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\CollectTaskNotFoundException;

class NullCollectTaskGateway implements CollectTaskGatewayInterface
{
    /** @var array<string, CollectTask> */
    private array $collectTasks = [];

    public function save(CollectTask $collectTask, bool $flush = true): void
    {
        $this->collectTasks[$collectTask->getId() ?? 'empty-id'] = $collectTask;
    }

    public function get(string $id): CollectTask
    {
        foreach ($this->collectTasks as $collectTask) {
            if ($collectTask->getId() === $id) {
                return $collectTask;
            }
        }

        throw CollectTaskNotFoundException::withId($id);
    }

    /**
     * @return array<CollectTask>
     */
    public function findActiveBySourceId(string $sourceId): array
    {
        return array_values(array_filter($this->collectTasks, function (CollectTask $task) use ($sourceId) {
            return $task->getSource()
                    ->getId() === $sourceId
                && \in_array($task->getStatus(), CollectTaskStatus::ACTIVE_STATUSES, true);
        }));
    }

    /**
     * @return array<CollectTask>
     */
    public function findActiveTasksByWatchFileId(string $watchFileId): array
    {
        return array_values(array_filter($this->collectTasks, function (CollectTask $task) use ($watchFileId) {
            return $task->getWatchFile()
                    ->getId() === $watchFileId
                && \in_array($task->getStatus(), CollectTaskStatus::ACTIVE_STATUSES, true);
        }));
    }

    /**
     * @return array<CollectTask>
     */
    public function findAllByWatchFileId(string $watchFileId): array
    {
        return array_values(array_filter($this->collectTasks, function (CollectTask $task) use ($watchFileId) {
            return $task->getWatchFile()
                ->getId() === $watchFileId;
        }));
    }

    public function clear(): void
    {
        $this->collectTasks = [];
    }

    /**
     * @return array<CollectTask>
     */
    public function getAll(): array
    {
        return array_values($this->collectTasks);
    }
}
