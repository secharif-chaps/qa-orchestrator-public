<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus\Stream;

use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;
use App\Domain\Collect\Stream\Event\CollectDataStreamDisconnectedEvent;
use App\Domain\Collect\Stream\Exception\CollectDataStreamException;
use App\Infrastructure\Collect\Bakus\Stream\BakusWebSocketPush;
use App\Infrastructure\Collect\Bakus\Stream\WebSocketMessageHandler;
use App\Tests\Units\Infrastructure\Collect\Bakus\Stream\Middleware\FakeWebSocketConnection;
use App\Tests\Units\Infrastructure\Collect\Stream\NullWebSocketServer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use WebSocket\Exception\ExceptionInterface;
use WebSocket\Message\Close;
use WebSocket\Message\Text;

#[CoversClass(BakusWebSocketPush::class)]
class BakusWebSocketPushTest extends TestCase
{
    private BakusWebSocketPush $push;
    private NullWebSocketServer $server;
    private EventDispatcherInterface&Stub $eventDispatcher;
    private WebSocketMessageHandler $messageHandler;
    private EntityManagerInterface&Stub $entityManager;
    private NullLogger $logger;

    protected function setUp(): void
    {
        $this->server = new NullWebSocketServer();
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->logger = new NullLogger();
        $this->buildPush();
    }

    private function buildPush(): void
    {
        $this->messageHandler = new WebSocketMessageHandler($this->eventDispatcher);
        $this->push = new BakusWebSocketPush(
            $this->server,
            $this->eventDispatcher,
            $this->messageHandler,
            $this->entityManager,
            $this->logger
        );
    }

    public function testListenSuccessfullyReturnsTrue(): void
    {
        $result = $this->push->listen();

        $this->assertTrue($result);
        $this->assertEquals(1, $this->server->getStartCallCount());
        $this->assertEquals(1, $this->server->getStopCallCount());
        $this->assertEquals(1, $this->server->getDisconnectCallCount());
        $this->assertFalse($this->push->isListening());
    }

    public function testListenThrowsExceptionWrapsInCollectDataStreamException(): void
    {
        $this->server->throwExceptionOnStart(new \RuntimeException('Server error'));

        $this->expectException(CollectDataStreamException::class);
        $this->expectExceptionMessage('Failed to connect to WebSocket: Server error');

        try {
            $this->push->listen();
        } finally {
            // Ensure stop and disconnect are called in finally block
            $this->assertEquals(1, $this->server->getStopCallCount());
            $this->assertEquals(1, $this->server->getDisconnectCallCount());
        }
    }

    public function testListenAlwaysCallsStopInFinallyBlock(): void
    {
        $this->server->throwExceptionOnStart(new \RuntimeException('Test exception'));

        try {
            $this->push->listen();
            $this->fail('Expected CollectDataStreamException was not thrown');
        } catch (CollectDataStreamException $e) {
            // Expected exception
            $this->assertEquals('Failed to connect to WebSocket: Test exception', $e->getMessage());
        }

        // Verify stop and disconnect were called even though exception was thrown
        $this->assertEquals(1, $this->server->getStopCallCount());
        $this->assertEquals(1, $this->server->getDisconnectCallCount());
        $this->assertFalse($this->push->isListening());
    }

    public function testOnTextCallbackWithValidIdsDispatchesEvent(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildPush();
        $connection = new FakeWebSocketConnection();
        $connection->setMeta('collect_task_id', 'task-123');
        $connection->setMeta('provider_task_id', 'provider-456');
        $connection->setMeta('connection_id', 'connection-789');

        $payload = [
            'type' => 'data',
            'content' => 'test',
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $message = new Text($payloadJson);

        $eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($payload) {
                return $event instanceof CollectDataReceivedEvent
                    && $event->data === $payload
                    && 'task-123' === $event->collectTaskId
                    && 'provider-456' === $event->providerTaskId;
            }));

        $this->push->listen();
        $this->server->triggerOnText($connection, $message);
    }

    public function testOnTextCallbackWithMissingCollectTaskIdLogsWarning(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildPush();
        $connection = new FakeWebSocketConnection();
        // No collect_task_id set
        $connection->setMeta('provider_task_id', 'provider-456');

        $payload = [
            'type' => 'data',
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $message = new Text($payloadJson);

        $eventDispatcherMock->expects($this->never())
            ->method('dispatch');

        $this->push->listen();
        $this->server->triggerOnText($connection, $message);
    }

    public function testOnTextCallbackWithNonStringCollectTaskIdLogsWarning(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildPush();
        $connection = new FakeWebSocketConnection();
        $connection->setMeta('collect_task_id', 123); // Integer instead of string
        $connection->setMeta('provider_task_id', 'provider-456');

        $payload = [
            'type' => 'data',
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $message = new Text($payloadJson);

        $eventDispatcherMock
            ->expects($this->never())
            ->method('dispatch');

        $this->push->listen();
        $this->server->triggerOnText($connection, $message);
    }

    public function testOnTextCallbackWithMissingProviderTaskIdLogsWarning(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildPush();
        $connection = new FakeWebSocketConnection();
        $connection->setMeta('collect_task_id', 'task-123');
        // No provider_task_id set

        $payload = [
            'type' => 'data',
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $message = new Text($payloadJson);

        $eventDispatcherMock->expects($this->never())
            ->method('dispatch');

        $this->push->listen();
        $this->server->triggerOnText($connection, $message);
    }

    public function testOnTextCallbackWithNonStringProviderTaskIdLogsWarning(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildPush();
        $connection = new FakeWebSocketConnection();
        $connection->setMeta('collect_task_id', 'task-123');
        $connection->setMeta('provider_task_id', null); // Null instead of string

        $payload = [
            'type' => 'data',
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $message = new Text($payloadJson);

        $eventDispatcherMock
            ->expects($this->never())
            ->method('dispatch');

        $this->push->listen();
        $this->server->triggerOnText($connection, $message);
    }

    public function testOnCloseCallbackWithValidIdsDispatchesEventAndClearsMetadata(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildPush();
        $connection = new FakeWebSocketConnection();
        $connection->setMeta('collect_task_id', 'task-123');
        $connection->setMeta('provider_task_id', 'provider-456');
        $connection->setMeta('connection_id', 'connection-789');

        $message = new Close(1000, 'Normal closure');

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof CollectDataStreamDisconnectedEvent
                    && 'task-123' === $event->collectTaskId
                    && 'provider-456' === $event->providerTaskId
                    && 1000 === $event->code
                    && 'Normal closure' === $event->reason; // Keep original reason, not code meaning
            }));

        $this->push->listen();
        $this->server->triggerOnClose($connection, $message);

        // Verify metadata is cleared
        $this->assertNull($connection->getMeta('collect_task_id'));
        $this->assertNull($connection->getMeta('provider_task_id'));
    }

    public function testOnCloseCallbackWithMissingIdsDoesNotDispatchEvent(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildPush();
        $connection = new FakeWebSocketConnection();
        // No metadata set

        $message = new Close(1000, 'Normal closure');

        $eventDispatcherMock->expects($this->never())
            ->method('dispatch');

        $this->push->listen();
        $this->server->triggerOnClose($connection, $message);

        // Verify metadata is cleared (even though it was already null)
        $this->assertNull($connection->getMeta('collect_task_id'));
        $this->assertNull($connection->getMeta('provider_task_id'));
    }

    public function testOnCloseWithCode1000LogsNormalClosure(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildPush();
        $connection = new FakeWebSocketConnection();
        $connection->setMeta('collect_task_id', 'task-123');
        $connection->setMeta('provider_task_id', 'provider-456');
        $connection->setMeta('connection_id', 'connection-789');

        $message = new Close(1000, 'Client closed');

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof CollectDataStreamDisconnectedEvent
                    && 1000 === $event->code;
            }));

        $this->push->listen();
        $this->server->triggerOnClose($connection, $message);
    }

    public function testOnCloseWithAbnormalCodeLogsAbnormalClosure(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildPush();
        $connection = new FakeWebSocketConnection();
        $connection->setMeta('collect_task_id', 'task-123');
        $connection->setMeta('provider_task_id', 'provider-456');
        $connection->setMeta('connection_id', 'connection-789');

        $message = new Close(1006, 'Connection lost');

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof CollectDataStreamDisconnectedEvent
                    && 1006 === $event->code
                    && 'Connection lost' === $event->reason; // Keep original reason when provided
            }));

        $this->push->listen();
        $this->server->triggerOnClose($connection, $message);
    }

    public function testOnCloseWithCodeButNoReasonUsesCodeMeaning(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildPush();
        $connection = new FakeWebSocketConnection();
        $connection->setMeta('collect_task_id', 'task-123');
        $connection->setMeta('provider_task_id', 'provider-456');
        $connection->setMeta('connection_id', 'connection-789');

        $message = new Close(1001, ''); // Empty reason

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof CollectDataStreamDisconnectedEvent
                    && 1001 === $event->code
                    && 'Going Away' === $event->reason; // Code meaning
            }));

        $this->push->listen();
        $this->server->triggerOnClose($connection, $message);
    }

    public function testOnErrorCallbackWithConnectionLogsAndClearsMetadata(): void
    {
        $connection = new FakeWebSocketConnection();
        $connection->setMeta('collect_task_id', 'task-123');
        $connection->setMeta('provider_task_id', 'provider-456');

        $exception = new class('Test error') extends \Exception implements ExceptionInterface {
        };

        $this->push->listen();
        $this->server->triggerOnError($connection, $exception);

        // Verify metadata is cleared
        $this->assertNull($connection->getMeta('collect_task_id'));
        $this->assertNull($connection->getMeta('provider_task_id'));
    }

    public function testOnErrorCallbackWithoutConnectionLogsWarning(): void
    {
        $exception = new class('Test error') extends \Exception implements ExceptionInterface {
        };

        // Should not crash when connection is null
        $this->push->listen();
        $this->server->triggerOnError(null, $exception);

        // Test passed if no exception was thrown
        $this->assertTrue(true); // @phpstan-ignore-line method.alreadyNarrowedType to test no exception thrown
    }

    public function testStopMethodCallsServerStopAndDisconnect(): void
    {
        $this->push->stop();

        $this->assertEquals(1, $this->server->getStopCallCount());
        $this->assertEquals(1, $this->server->getDisconnectCallCount());
        $this->assertFalse($this->push->isListening());
    }

    public function testIsListeningReturnsCorrectState(): void
    {
        $this->assertFalse($this->push->isListening(), 'Should not be listening initially');

        // isListening is set to true during listen() but reset to false in finally block
        $this->push->listen();
        $this->assertFalse($this->push->isListening(), 'Should not be listening after listen() completes');
    }

    public function testOnCloseWithNullCodeAndEmptyReason(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildPush();
        $connection = new FakeWebSocketConnection();
        $connection->setMeta('collect_task_id', 'task-123');
        $connection->setMeta('provider_task_id', 'provider-456');
        $connection->setMeta('connection_id', 'connection-789');

        $message = new Close(null, ''); // Close requires string, not null

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof CollectDataStreamDisconnectedEvent
                    && null === $event->code
                    && '' === $event->reason; // Empty reason stays empty when code is null
            }));

        $this->push->listen();
        $this->server->triggerOnClose($connection, $message);
    }

    public function testOnCloseWithUnknownCloseCode(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildPush();
        $connection = new FakeWebSocketConnection();
        $connection->setMeta('collect_task_id', 'task-123');
        $connection->setMeta('provider_task_id', 'provider-456');
        $connection->setMeta('connection_id', 'connection-789');

        $message = new Close(9999, ''); // Unknown code

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof CollectDataStreamDisconnectedEvent
                    && 9999 === $event->code
                    && 'Unknown code (9999)' === $event->reason;
            }));

        $this->push->listen();
        $this->server->triggerOnClose($connection, $message);
    }

    public function testOnTextHandlesInvalidJsonGracefully(): void
    {
        $connection = new FakeWebSocketConnection();
        $connection->setMeta('collect_task_id', 'task-123');
        $connection->setMeta('provider_task_id', 'provider-456');

        $message = new Text('invalid json');

        // Should not crash - WebSocketMessageHandler handles this
        $this->push->listen();
        $this->server->triggerOnText($connection, $message);

        // Test passed if no exception was thrown
        $this->assertTrue(true); // @phpstan-ignore-line method.alreadyNarrowedType to test no exception thrown
    }

    public function testMultipleMessagesCanBeProcessed(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildPush();
        $connection = new FakeWebSocketConnection();
        $connection->setMeta('collect_task_id', 'task-123');
        $connection->setMeta('provider_task_id', 'provider-456');
        $connection->setMeta('connection_id', 'connection-789');

        $payload1 = [
            'type' => 'data',
            'content' => 'message 1',
        ];
        $payload1Json = json_encode($payload1);
        $this->assertIsString($payload1Json);
        $message1 = new Text($payload1Json);

        $payload2 = [
            'type' => 'data',
            'content' => 'message 2',
        ];
        $payload2Json = json_encode($payload2);
        $this->assertIsString($payload2Json);
        $message2 = new Text($payload2Json);

        $eventDispatcherMock->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(CollectDataReceivedEvent::class));

        $this->push->listen();
        $this->server->triggerOnText($connection, $message1);
        $this->server->triggerOnText($connection, $message2);
    }
}
