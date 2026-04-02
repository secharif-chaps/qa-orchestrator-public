<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus\Stream;

use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;
use App\Infrastructure\Collect\Bakus\Stream\WebSocketMessageHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(WebSocketMessageHandler::class)]
class WebSocketMessageHandlerTest extends TestCase
{
    private WebSocketMessageHandler $handler;

    /** @var EventDispatcherInterface&\PHPUnit\Framework\MockObject\MockObject */
    private EventDispatcherInterface $eventDispatcher;
    private NullLogger $logger;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = new NullLogger();
        $this->handler = new WebSocketMessageHandler($this->eventDispatcher, $this->logger);
    }

    public function testHandleValidMessage(): void
    {
        $payload = json_encode([
            'type' => 'data',
            'content' => 'test',
        ]);
        $this->assertIsString($payload);

        $collectTaskId = 'task-123';
        $providerTaskId = 'provider-456';

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($collectTaskId, $providerTaskId) {
                return $event instanceof CollectDataReceivedEvent
                    && $event->collectTaskId === $collectTaskId
                    && $event->providerTaskId === $providerTaskId
                    && $event->data === [
                        'type' => 'data',
                        'content' => 'test',
                    ];
            }))
            ->willReturnArgument(0);

        $result = ($this->handler)($payload, $collectTaskId, $providerTaskId);

        $this->assertInstanceOf(CollectDataReceivedEvent::class, $result);
        $this->assertSame($collectTaskId, $result->collectTaskId);
        $this->assertSame($providerTaskId, $result->providerTaskId);
    }

    public function testHandleInvalidJsonDoesNotDispatchEvent(): void
    {
        $payload = 'invalid json {';
        $collectTaskId = 'task-123';
        $providerTaskId = 'provider-456';

        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $result = ($this->handler)($payload, $collectTaskId, $providerTaskId);

        $this->assertNull($result);
    }

    public function testHandleNonArrayJsonDoesNotDispatchEvent(): void
    {
        $payload = json_encode('string instead of array');
        $this->assertIsString($payload);

        $collectTaskId = 'task-123';
        $providerTaskId = 'provider-456';

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $result = ($this->handler)($payload, $collectTaskId, $providerTaskId);

        $this->assertNull($result);
    }

    public function testHandleMessageWithoutTypeFieldDoesNotDispatchEvent(): void
    {
        $payload = json_encode([
            'content' => 'test without type',
        ]);
        $this->assertIsString($payload);

        $collectTaskId = 'task-123';
        $providerTaskId = 'provider-456';

        // Without type field, validation fails and no event is dispatched
        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $result = ($this->handler)($payload, $collectTaskId, $providerTaskId);

        $this->assertNull($result);
    }

    public function testHandleMessageWithNonStringTypeFieldDoesNotDispatchEvent(): void
    {
        $payload = json_encode([
            'type' => 123,
            'content' => 'test',
        ]);
        $this->assertIsString($payload);

        $collectTaskId = 'task-123';
        $providerTaskId = 'provider-456';

        // With invalid type field, validation fails and no event is dispatched
        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $result = ($this->handler)($payload, $collectTaskId, $providerTaskId);

        $this->assertNull($result);
    }

    public function testHandleMessageWithNonStringKeysDoesNotDispatchEvent(): void
    {
        $payload = '{"type":"data","0":"invalid key"}';
        $collectTaskId = 'task-123';
        $providerTaskId = 'provider-456';

        // With non-string keys, validation fails and no event is dispatched
        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $result = ($this->handler)($payload, $collectTaskId, $providerTaskId);

        $this->assertNull($result);
    }

    public function testHandleComplexValidMessage(): void
    {
        $data = [
            'type' => 'document',
            'id' => 'doc-123',
            'content' => 'Lorem ipsum',
            'metadata' => [
                'author' => 'John Doe',
                'created_at' => '2024-01-01',
            ],
        ];
        $payload = json_encode($data);
        $this->assertIsString($payload);

        $collectTaskId = 'task-123';
        $providerTaskId = 'provider-456';

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($data) {
                return $event instanceof CollectDataReceivedEvent
                    && $event->data === $data;
            }))
            ->willReturnArgument(0);

        $result = ($this->handler)($payload, $collectTaskId, $providerTaskId);

        $this->assertInstanceOf(CollectDataReceivedEvent::class, $result);
    }

    public function testHandleEmptyArrayMessage(): void
    {
        $payload = json_encode([]);
        $this->assertIsString($payload);

        $collectTaskId = 'task-123';
        $providerTaskId = 'provider-456';

        // Empty array has no type field, validation fails and no event is dispatched
        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $result = ($this->handler)($payload, $collectTaskId, $providerTaskId);

        $this->assertNull($result);
    }

    public function testHandleMessageWithSpecialCharacters(): void
    {
        $data = [
            'type' => 'data',
            'content' => 'Special chars: é à ñ 中文 🎉',
        ];
        $payload = json_encode($data);
        $this->assertIsString($payload);

        $collectTaskId = 'task-123';
        $providerTaskId = 'provider-456';

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($data) {
                return $event instanceof CollectDataReceivedEvent
                    && $event->data === $data;
            }))
            ->willReturnArgument(0);

        $result = ($this->handler)($payload, $collectTaskId, $providerTaskId);

        $this->assertInstanceOf(CollectDataReceivedEvent::class, $result);
    }

    public function testHandleMessageWithNestedArrays(): void
    {
        $data = [
            'type' => 'data',
            'nested' => [
                'level1' => [
                    'level2' => [
                        'value' => 'deep',
                    ],
                ],
            ],
        ];
        $payload = json_encode($data);
        $this->assertIsString($payload);

        $collectTaskId = 'task-123';
        $providerTaskId = 'provider-456';

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($data) {
                return $event instanceof CollectDataReceivedEvent
                    && $event->data === $data;
            }))
            ->willReturnArgument(0);

        $result = ($this->handler)($payload, $collectTaskId, $providerTaskId);

        $this->assertInstanceOf(CollectDataReceivedEvent::class, $result);
    }

    public function testHandleMalformedJsonWithExtraCommas(): void
    {
        $payload = '{"type":"data",,}';
        $collectTaskId = 'task-123';
        $providerTaskId = 'provider-456';

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $result = ($this->handler)($payload, $collectTaskId, $providerTaskId);

        $this->assertNull($result);
    }

    public function testHandleVeryLargeJsonDepth(): void
    {
        // Create a deeply nested structure (should be handled correctly up to 512 levels)
        $data = [
            'type' => 'data',
        ];
        $current = &$data;
        for ($i = 0; $i < 100; ++$i) {
            $current['nested'] = [];
            $current = &$current['nested'];
        }
        $current['value'] = 'deep';

        $payload = json_encode($data);
        $this->assertIsString($payload);

        $collectTaskId = 'task-123';
        $providerTaskId = 'provider-456';

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CollectDataReceivedEvent::class))
            ->willReturnArgument(0);

        $result = ($this->handler)($payload, $collectTaskId, $providerTaskId);

        $this->assertInstanceOf(CollectDataReceivedEvent::class, $result);
    }

    public function testHandleMessageWithAllScalarTypes(): void
    {
        $data = [
            'type' => 'data',
            'string' => 'value',
            'integer' => 42,
            'float' => 3.14,
            'boolean_true' => true,
            'boolean_false' => false,
            'null' => null,
        ];
        $payload = json_encode($data);
        $this->assertIsString($payload);

        $collectTaskId = 'task-123';
        $providerTaskId = 'provider-456';

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($data) {
                return $event instanceof CollectDataReceivedEvent
                    && $event->data === $data;
            }))
            ->willReturnArgument(0);

        $result = ($this->handler)($payload, $collectTaskId, $providerTaskId);

        $this->assertInstanceOf(CollectDataReceivedEvent::class, $result);
    }

    public function testHandleDispatchExceptionReturnsNull(): void
    {
        $payload = json_encode([
            'type' => 'data',
            'content' => 'test',
        ]);
        $this->assertIsString($payload);

        $collectTaskId = 'task-123';
        $providerTaskId = 'provider-456';

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('Dispatch error'));

        $result = ($this->handler)($payload, $collectTaskId, $providerTaskId);

        $this->assertNull($result);
    }
}
