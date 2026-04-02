<?php

declare(strict_types=1);

namespace App\Domain\Collect\Event;

use App\Domain\Collect\CollectTask;

class CollectTaskResumedEvent
{
    public function __construct(
        public readonly CollectTask $collectTask,
    ) {
    }
}
