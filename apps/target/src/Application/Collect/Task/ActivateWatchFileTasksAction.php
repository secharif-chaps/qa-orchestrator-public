<?php

declare(strict_types=1);

namespace App\Application\Collect\Task;

final readonly class ActivateWatchFileTasksAction
{
    public function __construct(
        public string $watchFileId,
    ) {
    }
}
