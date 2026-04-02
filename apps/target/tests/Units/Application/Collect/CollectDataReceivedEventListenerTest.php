<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect;

use App\Application\Collect\CollectDataReceivedEventListener;
use App\Domain\Collect\CollectDataHandlerInterface;
use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class CollectDataReceivedEventListenerTest extends TestCase
{
    private CollectDataReceivedEventListener $listener;
    private CollectDataHandlerInterface&MockObject $handler1;
    private CollectDataHandlerInterface&Stub $handler2;
    private CollectDataHandlerInterface&Stub $handler3;

    protected function setUp(): void
    {
        $this->handler1 = $this->createMock(CollectDataHandlerInterface::class);
        $this->handler2 = $this->createStub(CollectDataHandlerInterface::class);
        $this->handler3 = $this->createStub(CollectDataHandlerInterface::class);
        $this->buildListener();
    }

    private function buildListener(): void
    {
        $this->listener = new CollectDataReceivedEventListener(
            new \ArrayIterator([$this->handler1, $this->handler2, $this->handler3]),
            null
        );
    }

    public function testInvokeWithNoHandlersSupportsEvent(): void
    {
        $handler2Mock = $this->createMock(CollectDataHandlerInterface::class);
        $this->handler2 = $handler2Mock;
        $handler3Mock = $this->createMock(CollectDataHandlerInterface::class);
        $this->handler3 = $handler3Mock;
        $this->buildListener();
        // Arrange
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'test_data',
                'content' => 'test content',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $this->handler1->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(false);

        $handler2Mock->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(false);

        $handler3Mock->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(false);

        // Act
        ($this->listener)($event);

        // Assert - No additional assertions needed as expectations are verified
    }

    public function testInvokeWithOneHandlerSupportsEvent(): void
    {
        $handler2Mock = $this->createMock(CollectDataHandlerInterface::class);
        $this->handler2 = $handler2Mock;
        $handler3Mock = $this->createMock(CollectDataHandlerInterface::class);
        $this->handler3 = $handler3Mock;
        $this->buildListener();
        // Arrange
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'test_data',
                'content' => 'test content',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $this->handler1->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(false);

        $handler2Mock->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(true);

        $handler3Mock->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(false);

        $handler2Mock->expects($this->once())
            ->method('__invoke')
            ->with($event);

        // Act
        ($this->listener)($event);

        // Assert - No additional assertions needed as expectations are verified
    }

    public function testInvokeWithMultipleHandlersSupportEvent(): void
    {
        $handler2Mock = $this->createMock(CollectDataHandlerInterface::class);
        $this->handler2 = $handler2Mock;
        $handler3Mock = $this->createMock(CollectDataHandlerInterface::class);
        $this->handler3 = $handler3Mock;
        $this->buildListener();
        // Arrange
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'test_data',
                'content' => 'test content',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $this->handler1->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(true);

        $handler2Mock->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(false);

        $handler3Mock->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(true);

        $this->handler1->expects($this->once())
            ->method('__invoke')
            ->with($event);

        $handler3Mock->expects($this->once())
            ->method('__invoke')
            ->with($event);

        // Act
        ($this->listener)($event);

        // Assert - No additional assertions needed as expectations are verified
    }

    public function testInvokeWithHandlerThrowsException(): void
    {
        $handler2Mock = $this->createMock(CollectDataHandlerInterface::class);
        $this->handler2 = $handler2Mock;
        $handler3Mock = $this->createMock(CollectDataHandlerInterface::class);
        $this->handler3 = $handler3Mock;
        $this->buildListener();
        // Arrange
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'test_data',
                'content' => 'test content',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $exception = new \RuntimeException('Handler processing failed', 500);

        $this->handler1->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(true);

        $handler2Mock->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(false);

        $handler3Mock->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(false);

        $this->handler1->expects($this->once())
            ->method('__invoke')
            ->with($event)
            ->willThrowException($exception);

        // Act
        ($this->listener)($event);

        // Assert - No additional assertions needed as expectations are verified
    }

    public function testInvokeWithMultipleHandlersOneThrowsException(): void
    {
        $handler2Mock = $this->createMock(CollectDataHandlerInterface::class);
        $this->handler2 = $handler2Mock;
        $handler3Mock = $this->createMock(CollectDataHandlerInterface::class);
        $this->handler3 = $handler3Mock;
        $this->buildListener();
        // Arrange
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'test_data',
                'content' => 'test content',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $exception = new \InvalidArgumentException('Invalid data format', 400);

        $this->handler1->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(true);

        $handler2Mock->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(true);

        $handler3Mock->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(false);

        $this->handler1->expects($this->once())
            ->method('__invoke')
            ->with($event)
            ->willThrowException($exception);

        $handler2Mock->expects($this->once())
            ->method('__invoke')
            ->with($event);

        // Act
        ($this->listener)($event);

        // Assert - No additional assertions needed as expectations are verified
    }

    public function testInvokeWithNullLogger(): void
    {
        // Arrange
        $listener = new CollectDataReceivedEventListener([$this->handler1], null);
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'test_data',
                'content' => 'test content',
            ],
            new \DateTimeImmutable('2024-01-15 10:30:00')
        );

        $this->handler1->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(true);

        $this->handler1->expects($this->once())
            ->method('__invoke')
            ->with($event);

        // Act
        ($listener)($event);

        // Assert - No additional assertions needed as expectations are verified
    }

    public function testInvokeWithComplexEventData(): void
    {
        $handler2Mock = $this->createMock(CollectDataHandlerInterface::class);
        $this->handler2 = $handler2Mock;
        $handler3Mock = $this->createMock(CollectDataHandlerInterface::class);
        $this->handler3 = $handler3Mock;
        $this->buildListener();
        // Arrange
        $complexData = [
            'type' => 'merged_result',
            'result' => [
                'hash_document_sha1' => 'abc123def456',
                'url' => 'https://example.com/document.pdf',
                'fqdn' => 'example.com',
                'content_type' => 'application/pdf',
                'ts_collection' => '2024-01-15T10:30:00Z',
            ],
            'document_origin' => 'raw',
            'metadata' => [
                'source' => 'test_source',
                'priority' => 'high',
            ],
        ];

        $event = new CollectDataReceivedEvent(
            'collect-task-789',
            'provider-task-101',
            $complexData,
            new \DateTimeImmutable('2024-01-15 11:45:30')
        );

        $this->handler1->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(true);

        $handler2Mock->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(false);

        $handler3Mock->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(false);

        $this->handler1->expects($this->once())
            ->method('__invoke')
            ->with($event);

        // Act
        ($this->listener)($event);

        // Assert - No additional assertions needed as expectations are verified
    }
}
