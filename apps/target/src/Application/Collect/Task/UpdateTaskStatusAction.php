<?php

declare(strict_types=1);

namespace App\Application\Collect\Task;

use App\Application\SyncActionInterface;
use App\Domain\Collect\CollectTaskStatus;

final readonly class UpdateTaskStatusAction implements SyncActionInterface
{
    public function __construct(
        public string $collectTaskId,
        public CollectTaskStatus $status,
    ) {
    }
}
