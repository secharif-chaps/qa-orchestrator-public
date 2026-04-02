<?php

declare(strict_types=1);

namespace App\Domain\Collect\Stream\Event;

readonly class CollectDataStreamConnectedEvent extends AbstractCollectEvent
{
    public function __construct(
        string $collectTaskId,
        string $providerTaskId,
        public \DateTimeImmutable $connectedAt = new \DateTimeImmutable(),
    ) {
        parent::__construct($collectTaskId, $providerTaskId);
    }
}
