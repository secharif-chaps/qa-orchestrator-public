<?php

declare(strict_types=1);

namespace App\Application\Collect\Task;

final readonly class CreateCollectTaskAction
{
    public function __construct(
        public string $sourceId,
        public string $watchFileId,
        public bool $start = true,
    ) {
    }
}
