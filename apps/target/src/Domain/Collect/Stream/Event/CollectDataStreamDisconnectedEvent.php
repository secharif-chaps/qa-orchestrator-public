<?php

declare(strict_types=1);

namespace App\Domain\Collect\Stream\Event;

readonly class CollectDataStreamDisconnectedEvent extends AbstractCollectEvent
{
    public function __construct(
        string $collectTaskId,
        string $providerTaskId,
        public ?int $code,
        public ?string $reason,
        public \DateTimeImmutable $disconnectedAt = new \DateTimeImmutable(),
    ) {
        parent::__construct($collectTaskId, $providerTaskId);
    }
}
