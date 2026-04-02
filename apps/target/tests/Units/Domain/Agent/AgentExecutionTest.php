<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Agent;

use App\Domain\Agent\AgentExecution;
use App\Domain\Agent\AgentExecutionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentExecution::class)]
class AgentExecutionTest extends TestCase
{
    public function testCreateAgentExecution(): void
    {
        $execution = new AgentExecution('exec-123', 'ChatSessionMessage');

        $this->assertNull($execution->getId());
        $this->assertSame('exec-123', $execution->getExecutionId());
        $this->assertSame('ChatSessionMessage', $execution->getCommandName());
        $this->assertSame(AgentExecutionStatus::Running, $execution->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $execution->getStartedAt());
        $this->assertNull($execution->getCompletedAt());
        $this->assertNull($execution->getMetadata());
    }

    public function testComplete(): void
    {
        $execution = new AgentExecution('exec-123', 'ChatSessionMessage');

        $execution->complete();

        $this->assertSame(AgentExecutionStatus::Completed, $execution->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $execution->getCompletedAt());
    }

    public function testFail(): void
    {
        $execution = new AgentExecution('exec-123', 'ChatSessionMessage');

        $execution->fail();

        $this->assertSame(AgentExecutionStatus::Failed, $execution->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $execution->getCompletedAt());
    }

    public function testCancel(): void
    {
        $execution = new AgentExecution('exec-123', 'ChatSessionMessage');

        $execution->cancel();

        $this->assertSame(AgentExecutionStatus::Cancelled, $execution->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $execution->getCompletedAt());
    }

    public function testTimeout(): void
    {
        $execution = new AgentExecution('exec-123', 'ChatSessionMessage');

        $execution->timeout();

        $this->assertSame(AgentExecutionStatus::TimedOut, $execution->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $execution->getCompletedAt());
    }

    public function testCannotCompleteFromTerminalStatus(): void
    {
        $execution = new AgentExecution('exec-123', 'ChatSessionMessage');
        $execution->complete();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/terminal status/');

        $execution->cancel();
    }

    public function testCannotCancelFromTerminalStatus(): void
    {
        $execution = new AgentExecution('exec-123', 'ChatSessionMessage');
        $execution->fail();

        $this->expectException(\LogicException::class);

        $execution->complete();
    }

    public function testCannotTimeoutFromTerminalStatus(): void
    {
        $execution = new AgentExecution('exec-123', 'ChatSessionMessage');
        $execution->cancel();

        $this->expectException(\LogicException::class);

        $execution->timeout();
    }
}
