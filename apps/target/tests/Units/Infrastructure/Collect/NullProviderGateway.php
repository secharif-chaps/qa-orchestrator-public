<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect;

use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\ProviderGatewayInterface;
use App\Domain\Collect\ValueObject\Collector;

class NullProviderGateway implements ProviderGatewayInterface
{
    /** @var array<string, CollectTaskStatus> */
    private array $taskStatuses = [];

    /** @var list<Collector> */
    private array $collectors = [];
    private ?\Exception $exceptionToThrow = null;

    public function createTask(CollectTask $collectTask): string
    {
        $taskId = 'provider-task-' . uniqid();
        $this->taskStatuses[$taskId] = CollectTaskStatus::QUEUED;

        return $taskId;
    }

    public function cancelTask(string $taskId): void
    {
        $this->taskStatuses[$taskId] = CollectTaskStatus::CANCELLED;
    }

    public function getTaskStatus(string $taskId): CollectTaskStatus
    {
        return $this->taskStatuses[$taskId] ?? CollectTaskStatus::FAILED;
    }

    /**
     * @return list<Collector>
     */
    public function getCollectors(): array
    {
        if (null !== $this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }

        return $this->collectors;
    }

    public function throwException(\Exception $exception): void
    {
        $this->exceptionToThrow = $exception;
    }

    public function setTaskStatus(string $taskId, CollectTaskStatus $status): void
    {
        $this->taskStatuses[$taskId] = $status;
    }

    /**
     * @param list<Collector> $collectors
     */
    public function setCollectors(array $collectors): void
    {
        $this->collectors = $collectors;
    }

    public function clear(): void
    {
        $this->taskStatuses = [];
        $this->collectors = [];
    }
}
