<?php

declare(strict_types=1);

namespace App\Domain\Agent;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class AgentExecution
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private string $executionId;

    #[ORM\Column(length: 255)]
    private string $commandName;

    #[ORM\Column(length: 50, enumType: AgentExecutionStatus::class, options: [
        'default' => 'running',
    ])]
    private AgentExecutionStatus $status = AgentExecutionStatus::Running;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    public function __construct(string $executionId, string $commandName)
    {
        $this->executionId = $executionId;
        $this->commandName = $commandName;
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getExecutionId(): string
    {
        return $this->executionId;
    }

    public function getCommandName(): string
    {
        return $this->commandName;
    }

    public function getStatus(): AgentExecutionStatus
    {
        return $this->status;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    /** @return array<string, mixed>|null */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function complete(): void
    {
        $this->assertNotTerminal();
        $this->status = AgentExecutionStatus::Completed;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function fail(): void
    {
        $this->assertNotTerminal();
        $this->status = AgentExecutionStatus::Failed;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        $this->assertNotTerminal();
        $this->status = AgentExecutionStatus::Cancelled;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function timeout(): void
    {
        $this->assertNotTerminal();
        $this->status = AgentExecutionStatus::TimedOut;
        $this->completedAt = new \DateTimeImmutable();
    }

    private function assertNotTerminal(): void
    {
        if ($this->status->isTerminal()) {
            throw new \LogicException(\sprintf(
                'Cannot transition AgentExecution %s from terminal status "%s"',
                $this->id ?? 'unknown',
                $this->status->value,
            ));
        }
    }
}
