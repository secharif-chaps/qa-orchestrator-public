<?php

declare(strict_types=1);

namespace App\Domain\Collect\Event;

use App\Domain\Collect\CollectTask;

class CollectTaskStartedEvent
{
    public function __construct(
        public readonly CollectTask $collectTask,
    ) {
    }
}
