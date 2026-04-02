<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Agent;

use App\Domain\Agent\AgentExecution;
use App\Domain\Agent\AgentExecutionGatewayInterface;
use App\Domain\Agent\AgentExecutionStatus;
use App\Tests\Utils\EntityUtilsTrait;
use Symfony\Component\Uid\Uuid;

class NullAgentExecutionGateway implements AgentExecutionGatewayInterface
{
    use EntityUtilsTrait;

    /** @var array<string, AgentExecution> */
    private array $executions = [];
    public ?AgentExecution $savedExecution = null;

    public function save(AgentExecution $execution): void
    {
        $this->savedExecution = $execution;

        if (null === $execution->getId()) {
            $this->forcePropertyValue($execution, Uuid::v4());
        }

        $this->executions[(string) $execution->getId()] = $execution;
    }

    public function findByExecutionId(string $executionId): ?AgentExecution
    {
        foreach ($this->executions as $execution) {
            if ($execution->getExecutionId() === $executionId) {
                return $execution;
            }
        }

        return null;
    }

    public function findStaleExecutions(int $timeoutSeconds): array
    {
        $threshold = new \DateTimeImmutable(\sprintf('-%d seconds', $timeoutSeconds));

        return array_values(array_filter(
            $this->executions,
            static fn (AgentExecution $execution): bool => AgentExecutionStatus::Running === $execution->getStatus()
                && $execution->getStartedAt() < $threshold,
        ));
    }

    public function addExecution(AgentExecution $execution): void
    {
        if (null === $execution->getId()) {
            $this->forcePropertyValue($execution, Uuid::v4());
        }

        $this->executions[(string) $execution->getId()] = $execution;
    }
}
