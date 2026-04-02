<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus\Client;

use App\Application\Collect\Auth\AuthenticateAction;
use App\Domain\Collect\Auth\AccessToken;
use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;
use App\Domain\Collect\Stream\Event\CollectDataStreamConnectedEvent;
use App\Domain\Collect\Stream\Event\CollectDataStreamDisconnectedEvent;
use App\Domain\Collect\Stream\Exception\AbnormallyCloseStreamException;
use App\Domain\Collect\Stream\Exception\CollectDataStreamException;
use App\Domain\Collect\Stream\Exception\ErrorClientStreamException;
use App\Infrastructure\Collect\Bakus\Client\BakusAbstractClient;
use App\Infrastructure\Collect\Bakus\Stream\BakusWebSocketClient;
use App\Infrastructure\Collect\Bakus\Stream\WebSocketClientFactory;
use App\Infrastructure\Collect\Bakus\Stream\WebSocketMessageHandler;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use WebSocket\Client;
use WebSocket\Connection;
use WebSocket\Exception\ExceptionInterface;
use WebSocket\Message\Close;
use WebSocket\Message\Text;

#[CoversClass(BakusWebSocketClient::class)]
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(BakusAbstractClient::class)]
class BakusWebSocketClientTest extends TestCase
{
    private BakusWebSocketClient $webSocketClient;
    private EventDispatcherInterface&Stub $eventDispatcher;
    private Client&MockObject $mockClient;
    private WebSocketClientFactory&Stub $mockClientFactory;
    private Connection $mockConnection;
    private MockClock $mockClock;
    private WebSocketMessageHandler $webSocketMessageHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = $this->createMock(Client::class);
        $this->mockConnection = $this->createStub(Connection::class);
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->mockClientFactory = $this->createStub(WebSocketClientFactory::class);
        $this->mockClock = new MockClock();
        $this->mockClientFactory
            ->method('__invoke')
            ->willReturn($this->mockClient);
        $this->buildWebSocketClient();
    }

    private function buildWebSocketClient(): void
    {
        $messageBus = new NullMessageBus(
            fn (object $message) => $message instanceof AuthenticateAction ? new AccessToken(
                'mock-token',
                time() + 3600
            ) : null
        );
        $this->webSocketMessageHandler = new WebSocketMessageHandler($this->eventDispatcher);
        $this->webSocketClient = new BakusWebSocketClient(
            '',
            $this->mockClientFactory,
            $this->eventDispatcher,
            $messageBus,
            $this->mockClock,
            $this->webSocketMessageHandler,
            maxReconnectAttempts: 5,
            maxBackoffSeconds: 60,
        );
    }

    public function testConnectSuccessfullyDispatchesConnectedEvent(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildWebSocketClient();
        $onHandshakeCallback = null;
        $this->configureMockClient(onHandshakeCallback: $onHandshakeCallback);

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CollectDataStreamConnectedEvent::class));

        $result = $this->webSocketClient->connect('task-1', 'query-1');
        $this->assertTrue($result); // Connection succeeds before finally block

        $this->assertNotNull($onHandshakeCallback, 'onHandshake callback was not configured.');
        $onHandshakeCallback($this->mockClient, $this->mockConnection, $this->createStub(
            RequestInterface::class
        ), $this->createStub(ResponseInterface::class));

        // Connection status is set to true in the handshake callback
        $this->assertTrue($this->getIsConnected());
    }

    public function testConnectDoesNothingIfAlreadyConnected(): void
    {
        $mockClientFactoryMock = $this->createMock(WebSocketClientFactory::class);
        $this->mockClientFactory = $mockClientFactoryMock;
        $this->buildWebSocketClient();
        $this->setIsConnected(true);

        $mockClientFactoryMock
            ->expects($this->never())
            ->method('__invoke');

        $this->mockClient
            ->expects($this->never())
            ->method('start');

        $this->webSocketClient->connect('task-1', 'query-1');
    }

    public function testConnectThrowsExceptionOnClientCreationFailure(): void
    {
        $this->mockClientFactory
            ->method('__invoke')
            ->willThrowException(new \RuntimeException('Factory failed'));

        $this->expectException(CollectDataStreamException::class);
        $this->expectExceptionMessage('Failed to connect to WebSocket: Factory failed');

        $this->webSocketClient->connect('task-1', 'query-1');
    }

    public function testReceivedTextMessageDispatchesDataReceivedEvent(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildWebSocketClient();
        $onTextCallback = null;
        $this->configureMockClient(onTextCallback: $onTextCallback);
        $payload = [
            'type' => 'data',
            'content' => 'test',
        ];
        $payloadJson = json_encode($payload);

        $this->assertIsString($payloadJson, 'Failed to encode payload to JSON.');

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(fn ($event) => $event instanceof CollectDataReceivedEvent && $event->data === $payload)
            );

        $this->webSocketClient->connect('task-1', 'query-1');

        $this->assertNotNull($onTextCallback, 'onText callback was not configured.');
        $message = new Text($payloadJson);
        $onTextCallback($this->mockClient, $this->mockConnection, $message);
    }

    public function testNormalClosureDispatchesDisconnectedEvent(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildWebSocketClient();
        $onCloseCallback = null;
        $this->configureMockClient(onCloseCallback: $onCloseCallback);

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CollectDataStreamDisconnectedEvent::class));

        $this->webSocketClient->connect('task-1', 'query-1');

        $this->assertNotNull($onCloseCallback, 'onClose callback was not configured.');
        $message = new Close(1000, 'Normal');
        $onCloseCallback($this->mockClient, $this->mockConnection, $message);
        $this->assertFalse($this->getIsConnected());
    }

    public function testDisconnectClosesConnection(): void
    {
        $this->setIsConnected(true);

        $reflection = new \ReflectionClass($this->webSocketClient);
        try {
            $property = $reflection->getProperty('client');
            $property->setValue($this->webSocketClient, $this->mockClient);
        } catch (\ReflectionException $e) {
            $this->fail('Failed to reflect property \'client\'. Does it exist? Error: ' . $e->getMessage());
        }

        $this->mockClient
            ->expects($this->once())
            ->method('disconnect');

        $this->webSocketClient->disconnect();

        $this->assertFalse($this->getIsConnected());
    }

    public function testConnectDoesNothingIfReconnecting(): void
    {
        $mockClientFactoryMock = $this->createMock(WebSocketClientFactory::class);
        $this->mockClientFactory = $mockClientFactoryMock;
        $this->buildWebSocketClient();
        $this->setIsReconnecting(true);

        $mockClientFactoryMock
            ->expects($this->never())
            ->method('__invoke');

        $this->webSocketClient->connect('task-1', 'query-1');
    }

    public function testBuildWebSocketUrlIsCorrect(): void
    {
        $mockClientFactoryMock = $this->createMock(WebSocketClientFactory::class);
        $this->mockClientFactory = $mockClientFactoryMock;
        $this->buildWebSocketClient();
        $baseUrl = 'ws://localhost:8080';
        $messageBus = new NullMessageBus(
            fn (object $message) => $message instanceof AuthenticateAction ? new AccessToken(
                'mock-token',
                time() + 3600
            ) : null
        );

        $webSocketClient = new BakusWebSocketClient(
            $baseUrl,
            $this->mockClientFactory,
            $this->eventDispatcher,
            $messageBus,
            $this->mockClock,
            $this->webSocketMessageHandler,
        );

        $expectedUrl = 'ws://localhost:8080/ws/queries/test%2Bquery%2Bwith%2Bspecial%2Bchars';

        $mockClientFactoryMock->expects($this->once())
            ->method('__invoke')
            ->with($expectedUrl)
            ->willReturn($this->mockClient);

        $this->configureMockClient();

        $webSocketClient->connect('task-1', 'test+query+with+special+chars');
    }

    public function testBuildWebSocketUrlStripsTrailingSlash(): void
    {
        $mockClientFactoryMock = $this->createMock(WebSocketClientFactory::class);
        $this->mockClientFactory = $mockClientFactoryMock;
        $this->buildWebSocketClient();
        $baseUrl = 'ws://localhost:8080/';
        $messageBus = new NullMessageBus(
            fn (object $message) => $message instanceof AuthenticateAction ? new AccessToken(
                'mock-token',
                time() + 3600
            ) : null
        );

        $webSocketClient = new BakusWebSocketClient(
            $baseUrl,
            $this->mockClientFactory,
            $this->eventDispatcher,
            $messageBus,
            $this->mockClock,
            $this->webSocketMessageHandler,
        );

        $expectedUrl = 'ws://localhost:8080/ws/queries/query-1';

        $mockClientFactoryMock->expects($this->once())
            ->method('__invoke')
            ->with($expectedUrl)
            ->willReturn($this->mockClient);

        $this->configureMockClient();

        // Expect disconnect and stop to be called in finally block
        $this->mockClient
            ->expects($this->once())
            ->method('disconnect');

        $this->mockClient
            ->expects($this->once())
            ->method('stop');

        $webSocketClient->connect('task-1', 'query-1');
    }

    public function testAbnormalClosureDispatchesDisconnectedEventAndTriggersReconnection(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildWebSocketClient();
        $onCloseCallback = null;
        $this->configureMockClient(onCloseCallback: $onCloseCallback);

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CollectDataStreamDisconnectedEvent::class));

        $this->webSocketClient->connect('task-1', 'query-1');

        $this->assertNotNull($onCloseCallback, 'onClose callback was not configured.');
        $message = new Close(1006, 'Connection lost');

        $this->expectException(AbnormallyCloseStreamException::class);
        $this->expectExceptionMessage('WebSocket connection closed abnormally with code 1006 (Connection lost)');

        $onCloseCallback($this->mockClient, $this->mockConnection, $message);
    }

    public function testCloseWithoutCodeUsesReasonMapping(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildWebSocketClient();
        $onCloseCallback = null;
        $this->configureMockClient(onCloseCallback: $onCloseCallback);

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CollectDataStreamDisconnectedEvent::class));

        $this->webSocketClient->connect('task-1', 'query-1');

        $this->assertNotNull($onCloseCallback, 'onClose callback was not configured.');
        $message = new Close(1001, '');

        $this->expectException(AbnormallyCloseStreamException::class);
        $this->expectExceptionMessage('WebSocket connection closed abnormally with code 1001 ()');

        $onCloseCallback($this->mockClient, $this->mockConnection, $message);
    }

    public function testDisconnectWhenClientIsNull(): void
    {
        $this->webSocketClient->disconnect();

        $this->assertFalse($this->getIsConnected());
    }

    public function testHandleMessageWithInvalidJson(): void
    {
        $onTextCallback = null;
        $this->configureMockClient(onTextCallback: $onTextCallback);

        $this->webSocketClient->connect('task-1', 'query-1');

        $this->assertNotNull($onTextCallback, 'onText callback was not configured.');
        $message = new Text('invalid json');
        $onTextCallback($this->mockClient, $this->mockConnection, $message);
    }

    public function testHandleMessageWithNonArrayData(): void
    {
        $onTextCallback = null;
        $this->configureMockClient(onTextCallback: $onTextCallback);

        $this->webSocketClient->connect('task-1', 'query-1');

        $this->assertNotNull($onTextCallback, 'onText callback was not configured.');
        $message = new Text('"string instead of object"');
        $onTextCallback($this->mockClient, $this->mockConnection, $message);
    }

    public function testHandleMessageWithMissingTypeField(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildWebSocketClient();
        $onTextCallback = null;
        $this->configureMockClient(onTextCallback: $onTextCallback);

        $eventDispatcherMock->expects($this->never())
            ->method('dispatch');

        $this->webSocketClient->connect('task-1', 'query-1');

        $this->assertNotNull($onTextCallback, 'onText callback was not configured.');
        $message = new Text('{"content": "test without type"}');
        $onTextCallback($this->mockClient, $this->mockConnection, $message);
    }

    public function testHandleMessageWithInvalidTypeField(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildWebSocketClient();
        $onTextCallback = null;
        $this->configureMockClient(onTextCallback: $onTextCallback);

        $eventDispatcherMock->expects($this->never())
            ->method('dispatch');

        $this->webSocketClient->connect('task-1', 'query-1');

        $this->assertNotNull($onTextCallback, 'onText callback was not configured.');
        $message = new Text('{"type": 123, "content": "test"}');
        $onTextCallback($this->mockClient, $this->mockConnection, $message);
    }

    public function testHandleMessageWithNonStringKeys(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildWebSocketClient();
        $onTextCallback = null;
        $this->configureMockClient(onTextCallback: $onTextCallback);

        $eventDispatcherMock->expects($this->never())
            ->method('dispatch');

        $this->webSocketClient->connect('task-1', 'query-1');

        $this->assertNotNull($onTextCallback, 'onText callback was not configured.');
        $message = new Text('{"type": "data", "0": "invalid key"}');
        $onTextCallback($this->mockClient, $this->mockConnection, $message);
    }

    public function testNormalClosureWithCode1000(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildWebSocketClient();
        $onCloseCallback = null;
        $this->configureMockClient(onCloseCallback: $onCloseCallback);

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(
                    fn (CollectDataStreamDisconnectedEvent $event) => 'Normal Closure' === $event->reason
                )
            );

        $this->webSocketClient->connect('task-1', 'query-1');

        $this->assertNotNull($onCloseCallback, 'onClose callback was not configured.');
        $message = new Close(1000, '');
        $onCloseCallback($this->mockClient, $this->mockConnection, $message);
        $this->assertFalse($this->getIsConnected());
    }

    public function testAbnormalClosureThrowsException(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildWebSocketClient();
        $onCloseCallback = null;
        $this->configureMockClient(onCloseCallback: $onCloseCallback);

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CollectDataStreamDisconnectedEvent::class));

        $this->webSocketClient->connect('task-1', 'query-1');

        $this->assertNotNull($onCloseCallback, 'onClose callback was not configured.');
        $message = new Close(1006, 'Abnormal closure');

        $this->expectException(AbnormallyCloseStreamException::class);
        $this->expectExceptionMessage('WebSocket connection closed abnormally with code 1006 (Abnormal closure)');

        $onCloseCallback($this->mockClient, $this->mockConnection, $message);
    }

    public function testOnErrorCallbackThrowsErrorStreamException(): void
    {
        $onErrorCallback = null;
        $this->configureMockClient(onErrorCallback: $onErrorCallback);

        $this->webSocketClient->connect('task-1', 'query-1');

        $this->assertNotNull($onErrorCallback, 'onError callback was not configured.');

        // Create a mock WebSocket exception
        $exception = new class('Test WebSocket error') extends \Exception implements ExceptionInterface {
        };

        $this->expectException(ErrorClientStreamException::class);
        $this->expectExceptionMessage('WebSocket error: Test WebSocket error');

        // Call the error callback - this should throw ErrorStreamException directly
        $onErrorCallback($this->mockClient, $this->mockConnection, $exception);
    }

    public function testAttemptReconnectionInNonCliEnvironmentLogsWarning(): void
    {
        $reflection = new \ReflectionClass($this->webSocketClient);
        $reconnectAttemptsProperty = $reflection->getProperty('reconnectAttempts');
        $reconnectAttemptsProperty->setValue($this->webSocketClient, 0);

        $onCloseCallback = null;
        $this->configureMockClient(onCloseCallback: $onCloseCallback);

        $this->webSocketClient->connect('task-1', 'query-1');

        $this->assertNotNull($onCloseCallback, 'onClose callback was not configured.');

        // Trigger abnormal closure which throws exception
        $message = new Close(1006, 'Abnormal closure');

        $this->expectException(AbnormallyCloseStreamException::class);
        $onCloseCallback($this->mockClient, $this->mockConnection, $message);
    }

    public function testCircuitBreakerActivationWhenAlreadyActive(): void
    {
        // Set circuit breaker as active
        $reflection = new \ReflectionClass($this->webSocketClient);
        $property = $reflection->getProperty('circuitBreakerActive');
        $property->setValue($this->webSocketClient, true);

        $this->expectException(CollectDataStreamException::class);
        $this->expectExceptionMessage('Circuit breaker is active, cannot connect to WebSocket');

        $this->webSocketClient->connect('task-1', 'query-1');
    }

    public function testAbnormalClosureWithReconnectionFlagSet(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildWebSocketClient();
        $onCloseCallback = null;
        $this->configureMockClient(onCloseCallback: $onCloseCallback);

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CollectDataStreamDisconnectedEvent::class));

        $this->webSocketClient->connect('task-1', 'query-1');

        $this->assertNotNull($onCloseCallback, 'onClose callback was not configured.');

        // Test that abnormal closure creates exception with correct reconnection info
        $message = new Close(1006, 'Connection lost');

        try {
            $onCloseCallback($this->mockClient, $this->mockConnection, $message);
            $this->fail('Expected AbnormallyCloseStreamException to be thrown');
        } catch (AbnormallyCloseStreamException $e) {
            $this->assertEquals(0, $e->attempts, 'Should start with 0 attempts');
            $this->assertTrue($e->willRetry, 'Should be willing to retry with 0 attempts');
            $this->assertEquals('Abnormal Closure', $e->codeMeaning);
            $this->assertEquals(1006, $e->getCode(), 'Exception code should match close status');
            $this->assertStringContainsString(
                'WebSocket connection closed abnormally with code 1006',
                $e->getMessage()
            );
        }
    }

    public function testMaxReconnectAttemptsConfiguration(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcherMock;
        $this->buildWebSocketClient();
        // Create client with custom max reconnect attempts for testing
        $messageBus = new NullMessageBus(
            fn (object $message) => $message instanceof AuthenticateAction ? new AccessToken(
                'mock-token',
                time() + 3600
            ) : null
        );

        $webSocketClient = new BakusWebSocketClient(
            '',
            $this->mockClientFactory,
            $this->eventDispatcher,
            $messageBus,
            $this->mockClock,
            $this->webSocketMessageHandler,
            maxReconnectAttempts: 3, // (instead of default 5)
            maxBackoffSeconds: 10 // (instead of default 60)
        );

        // Set reconnect attempts to max to test the boundary
        $reflection = new \ReflectionClass($webSocketClient);
        $reconnectAttemptsProperty = $reflection->getProperty('reconnectAttempts');
        $reconnectAttemptsProperty->setValue($webSocketClient, 3); // At max attempts

        $onCloseCallback = null;
        $this->configureMockClient(onCloseCallback: $onCloseCallback);

        $eventDispatcherMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CollectDataStreamDisconnectedEvent::class));

        $webSocketClient->connect('task-1', 'query-1');

        $this->assertNotNull($onCloseCallback, 'onClose callback was not configured.');

        // Test abnormal closure when max attempts reached
        $message = new Close(1006, 'Connection lost');

        try {
            $onCloseCallback($this->mockClient, $this->mockConnection, $message);
            $this->fail('Expected AbnormallyCloseStreamException to be thrown');
        } catch (AbnormallyCloseStreamException $e) {
            $this->assertEquals(3, $e->attempts, 'Should show current attempt count');
            $this->assertFalse($e->willRetry, 'Should not retry when max attempts reached');
            $this->assertEquals('Abnormal Closure', $e->codeMeaning);
        }
    }

    public function testIsConnectedReturnsCorrectStatus(): void
    {
        $this->assertFalse($this->webSocketClient->isConnected(), 'Should not be connected initially');

        $this->setIsConnected(true);
        $this->assertTrue($this->webSocketClient->isConnected(), 'Should return true when connected');

        $this->setIsConnected(false);
        $this->assertFalse($this->webSocketClient->isConnected(), 'Should return false when disconnected');
    }

    private function configureMockClient(
        ?callable &$onHandshakeCallback = null,
        ?callable &$onTextCallback = null,
        ?callable &$onCloseCallback = null,
        ?callable &$onErrorCallback = null,
    ): void {
        $this->mockClient
            ->method('addHeader')
            ->willReturnSelf();

        $this->mockClient->method('start');

        $this->mockClient
            ->method('onHandshake')
            ->willReturnCallback(
                function (callable $callback) use (&$onHandshakeCallback) {
                    $onHandshakeCallback = $callback;

                    return $this->mockClient;
                }
            );

        $this->mockClient
            ->method('onText')
            ->willReturnCallback(
                function (callable $callback) use (&$onTextCallback) {
                    $onTextCallback = $callback;

                    return $this->mockClient;
                }
            );

        $this->mockClient
            ->method('onClose')
            ->willReturnCallback(
                function (callable $callback) use (&$onCloseCallback) {
                    $onCloseCallback = $callback;

                    return $this->mockClient;
                }
            );

        $this->mockClient
            ->method('onError')
            ->willReturnCallback(
                function (callable $callback) use (&$onErrorCallback) {
                    $onErrorCallback = $callback;

                    return $this->mockClient;
                }
            );
    }

    private function setIsConnected(bool $status): void
    {
        $reflection = new \ReflectionClass($this->webSocketClient);
        $property = $reflection->getProperty('isConnected');
        $property->setValue($this->webSocketClient, $status);
    }

    private function getIsConnected(): bool
    {
        $reflection = new \ReflectionClass($this->webSocketClient);
        $property = $reflection->getProperty('isConnected');
        $value = $property->getValue($this->webSocketClient);

        $this->assertIsBool($value, 'isConnected property is not a boolean.');

        return $value;
    }

    private function setIsReconnecting(bool $status): void
    {
        $reflection = new \ReflectionClass($this->webSocketClient);
        $property = $reflection->getProperty('isReconnecting');
        $property->setValue($this->webSocketClient, $status);
    }
}
