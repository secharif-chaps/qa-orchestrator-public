<?php

declare(strict_types=1);

namespace App\Application\Actor;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ActorDetectHandler
{
    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ActorDetectAction $action): void
    {
        $this->logger?->info('Not implemented yet, should be triggered by rabbitmq task', [
            'action' => $action,
        ]);
    }
}
