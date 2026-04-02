<?php

declare(strict_types=1);

namespace App\Domain\Agent;

interface AgentExecutionGatewayInterface
{
    public function save(AgentExecution $execution): void;

    public function findByExecutionId(string $executionId): ?AgentExecution;

    /**
     * @return list<AgentExecution>
     */
    public function findStaleExecutions(int $timeoutSeconds): array;
}
