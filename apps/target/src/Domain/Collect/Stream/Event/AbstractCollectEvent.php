<?php

namespace App\Domain\Collect\Stream\Event;

abstract readonly class AbstractCollectEvent
{
    public function __construct(
        public string $collectTaskId,
        public string $providerTaskId,
    ) {
    }
}
