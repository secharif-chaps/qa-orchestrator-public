<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus\Handler;

use App\Application\Collect\Task\UpdateTaskStatusAction;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;
use App\Infrastructure\Collect\Bakus\BakusStatusMapper;
use App\Infrastructure\Collect\Bakus\Handler\BakusQueryStatusHandler;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\TestCase;

class BakusQueryStatusHandlerTest extends TestCase
{
    private BakusQueryStatusHandler $handler;
    private NullMessageBus $messageBus;
    private BakusStatusMapper $statusMapper;

    protected function setUp(): void
    {
        $this->messageBus = new NullMessageBus();
        $this->statusMapper = new BakusStatusMapper();
        $this->handler = new BakusQueryStatusHandler($this->messageBus, $this->statusMapper, null);
    }

    public function testSupportsWithQueryStatusType(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'query_status',
                'state' => 'pending',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $this->assertTrue($this->handler->supports($event));
    }

    public function testSupportsWithDifferentType(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'other_type',
                'state' => 'pending',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $this->assertFalse($this->handler->supports($event));
    }

    public function testSupportsWithMissingType(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'state' => 'pending',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $this->assertFalse($this->handler->supports($event));
    }

    public function testInvokeWithValidPendingStatus(): void
    {
        $event = $this->createQueryStatusEvent('pending');

        ($this->handler)($event);

        $this->assertCount(1, $this->messageBus->getDispatchedMessages());
        $action = $this->messageBus->getDispatchedMessages()[0];
        $this->assertInstanceOf(UpdateTaskStatusAction::class, $action);
        $this->assertSame('collect-task-123', $action->collectTaskId);
        $this->assertSame(CollectTaskStatus::QUEUED, $action->status);
    }

    public function testInvokeWithValidInProgressStatus(): void
    {
        $event = $this->createQueryStatusEvent('in_progress');

        ($this->handler)($event);

        $this->assertCount(1, $this->messageBus->getDispatchedMessages());
        $action = $this->messageBus->getDispatchedMessages()[0];
        $this->assertInstanceOf(UpdateTaskStatusAction::class, $action);
        $this->assertSame(CollectTaskStatus::RUNNING, $action->status);
    }

    public function testInvokeWithValidDoneStatus(): void
    {
        $event = $this->createQueryStatusEvent('done');

        ($this->handler)($event);

        $this->assertCount(1, $this->messageBus->getDispatchedMessages());
        $action = $this->messageBus->getDispatchedMessages()[0];
        $this->assertInstanceOf(UpdateTaskStatusAction::class, $action);
        $this->assertSame(CollectTaskStatus::COMPLETED, $action->status);
    }

    public function testInvokeWithMissingStateKey(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'query_status',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        ($this->handler)($event);

        $this->assertCount(0, $this->messageBus->getDispatchedMessages());
    }

    public function testInvokeWithNonStringState(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'query_status',
                'state' => 12345,
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        ($this->handler)($event);

        $this->assertCount(0, $this->messageBus->getDispatchedMessages());
    }

    public function testInvokeWithNullState(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'query_status',
                'state' => null,
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        ($this->handler)($event);

        $this->assertCount(0, $this->messageBus->getDispatchedMessages());
    }

    public function testInvokeWithEmptyStringState(): void
    {
        $event = $this->createQueryStatusEvent('');

        ($this->handler)($event);

        $this->assertCount(1, $this->messageBus->getDispatchedMessages());
        $action = $this->messageBus->getDispatchedMessages()[0];
        $this->assertInstanceOf(UpdateTaskStatusAction::class, $action);
        $this->assertSame(CollectTaskStatus::FAILED, $action->status);
    }

    public function testInvokeWithCanceledStatus(): void
    {
        $event = $this->createQueryStatusEvent('canceled');

        ($this->handler)($event);

        $this->assertCount(1, $this->messageBus->getDispatchedMessages());
        $action = $this->messageBus->getDispatchedMessages()[0];
        $this->assertInstanceOf(UpdateTaskStatusAction::class, $action);
        $this->assertSame(CollectTaskStatus::CANCELLED, $action->status);
    }

    private function createQueryStatusEvent(string $state): CollectDataReceivedEvent
    {
        return new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'query_status',
                'state' => $state,
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );
    }
}
