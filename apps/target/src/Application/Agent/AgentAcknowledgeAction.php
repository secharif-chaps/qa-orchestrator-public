<?php

declare(strict_types=1);

namespace App\Application\Agent;

readonly class AgentAcknowledgeAction
{
    public function __construct(
        public string $executionId,
        public string $commandName,
        public ?string $conversationId = null,
    ) {
    }
}
