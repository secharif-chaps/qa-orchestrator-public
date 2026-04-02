<?php

declare(strict_types=1);

namespace App\Domain\Agent;

interface AgentExecutionCancellerInterface
{
    /**
     * Attempt to cancel a running agent execution.
     */
    public function cancel(string $executionId): void;
}
