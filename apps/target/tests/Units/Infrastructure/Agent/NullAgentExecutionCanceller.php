<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Agent;

use App\Domain\Agent\AgentExecutionCancellerInterface;

class NullAgentExecutionCanceller implements AgentExecutionCancellerInterface
{
    /** @var list<string> */
    public array $cancelledExecutionIds = [];

    public function cancel(string $executionId): void
    {
        $this->cancelledExecutionIds[] = $executionId;
    }
}
