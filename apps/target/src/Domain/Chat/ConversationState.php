<?php

declare(strict_types=1);

namespace App\Domain\Chat;

enum ConversationState: string
{
    case Idle = 'idle';
    case WaitingForAgent = 'waiting_for_agent';
    case AgentProcessing = 'agent_processing';
    public const array CANCELABLE_STATES = [self::WaitingForAgent, self::AgentProcessing];

    public function isWaitingForAgent(): bool
    {
        return self::WaitingForAgent === $this;
    }

    public function canBeCancelled(): bool
    {
        return \in_array($this, self::CANCELABLE_STATES, true);
    }
}
