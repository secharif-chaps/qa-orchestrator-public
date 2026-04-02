<?php

declare(strict_types=1);

namespace App\Domain\Agent;

enum AgentExecutionStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';

    public function isTerminal(): bool
    {
        return \in_array($this, [self::Completed, self::Failed, self::Cancelled, self::TimedOut], true);
    }
}
