<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Collect;

use App\Domain\Collect\CollectTaskStatus;
use PHPUnit\Framework\TestCase;

class CollectTaskStatusTest extends TestCase
{
    public function testStatusTransitions(): void
    {
        // CREATED can transition to QUEUED or CANCELLED
        $this->assertTrue(CollectTaskStatus::CREATED->canTransitionTo(CollectTaskStatus::QUEUED));
        $this->assertTrue(CollectTaskStatus::CREATED->canTransitionTo(CollectTaskStatus::CANCELLED));
        $this->assertFalse(CollectTaskStatus::CREATED->canTransitionTo(CollectTaskStatus::RUNNING));
        $this->assertFalse(CollectTaskStatus::CREATED->canTransitionTo(CollectTaskStatus::COMPLETED));

        // QUEUED can transition to RUNNING or CANCELLED
        $this->assertTrue(CollectTaskStatus::QUEUED->canTransitionTo(CollectTaskStatus::RUNNING));
        $this->assertTrue(CollectTaskStatus::QUEUED->canTransitionTo(CollectTaskStatus::CANCELLED));
        $this->assertFalse(CollectTaskStatus::QUEUED->canTransitionTo(CollectTaskStatus::CREATED));

        // RUNNING can transition to COMPLETED, FAILED, or CANCELLED
        $this->assertTrue(CollectTaskStatus::RUNNING->canTransitionTo(CollectTaskStatus::COMPLETED));
        $this->assertTrue(CollectTaskStatus::RUNNING->canTransitionTo(CollectTaskStatus::FAILED));
        $this->assertTrue(CollectTaskStatus::RUNNING->canTransitionTo(CollectTaskStatus::CANCELLED));
        $this->assertFalse(CollectTaskStatus::RUNNING->canTransitionTo(CollectTaskStatus::QUEUED));

        // Terminal states cannot transition
        $this->assertFalse(CollectTaskStatus::COMPLETED->canTransitionTo(CollectTaskStatus::RUNNING));
        $this->assertFalse(CollectTaskStatus::FAILED->canTransitionTo(CollectTaskStatus::RUNNING));
        $this->assertFalse(CollectTaskStatus::CANCELLED->canTransitionTo(CollectTaskStatus::RUNNING));
    }

    public function testTerminalStates(): void
    {
        $this->assertFalse(CollectTaskStatus::CREATED->isTerminal());
        $this->assertFalse(CollectTaskStatus::QUEUED->isTerminal());
        $this->assertFalse(CollectTaskStatus::RUNNING->isTerminal());

        // Terminal states
        $this->assertTrue(CollectTaskStatus::COMPLETED->isTerminal());
        $this->assertTrue(CollectTaskStatus::FAILED->isTerminal());
        $this->assertTrue(CollectTaskStatus::CANCELLED->isTerminal());
    }

    public function testStatusCheckers(): void
    {
        // Test isActive based on ACTIVE_STATUSES constant
        $this->assertTrue(CollectTaskStatus::CREATED->isActive());
        $this->assertTrue(CollectTaskStatus::QUEUED->isActive());
        $this->assertTrue(CollectTaskStatus::RUNNING->isActive());
        $this->assertFalse(CollectTaskStatus::COMPLETED->isActive());
        $this->assertFalse(CollectTaskStatus::FAILED->isActive());
        $this->assertFalse(CollectTaskStatus::CANCELLED->isActive());

        $this->assertTrue(CollectTaskStatus::COMPLETED->isCompleted());
        $this->assertFalse(CollectTaskStatus::RUNNING->isCompleted());

        $this->assertTrue(CollectTaskStatus::FAILED->isFailed());
        $this->assertFalse(CollectTaskStatus::COMPLETED->isFailed());

        $this->assertTrue(CollectTaskStatus::CANCELLED->isCancelled());
        $this->assertFalse(CollectTaskStatus::RUNNING->isCancelled());
    }

    public function testInvalidTransitionException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transition from created to completed');

        CollectTaskStatus::CREATED->throwIfInvalidTransition(CollectTaskStatus::COMPLETED);
    }

    public function testSameStateTransition(): void
    {
        // Same state transitions should be allowed
        $this->assertTrue(CollectTaskStatus::RUNNING->canTransitionTo(CollectTaskStatus::RUNNING));

        // Should not throw exception
        CollectTaskStatus::RUNNING->throwIfInvalidTransition(CollectTaskStatus::RUNNING);

        /*
         * @phpstan-ignore-next-line method.alreadyNarrowedType I used a fake assert here:
         * If we reach here, it means no exception was thrown for same state transition
         */
        $this->assertTrue(true, 'No exception thrown for same state transition');
    }
}
