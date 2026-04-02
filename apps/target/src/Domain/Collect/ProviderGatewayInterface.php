<?php

declare(strict_types=1);

namespace App\Domain\Collect;

use App\Domain\Collect\ValueObject\Collector;

interface ProviderGatewayInterface
{
    public function createTask(CollectTask $collectTask): string;

    public function cancelTask(string $taskId): void;

    public function getTaskStatus(string $taskId): CollectTaskStatus;

    /**
     * @return list<Collector>
     */
    public function getCollectors(): array;
}
