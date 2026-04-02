<?php

declare(strict_types=1);

namespace App\Domain\Collect\Stream\Event;

readonly class CollectStreamCircuitBreakerActivatedEvent extends AbstractCollectEvent
{
    public function __construct(
        string $collectTaskId,
        string $providerTaskId,
        public int $failedAttempts,
        public ?string $lastError = null,
    ) {
        parent::__construct($collectTaskId, $providerTaskId);
    }
}
