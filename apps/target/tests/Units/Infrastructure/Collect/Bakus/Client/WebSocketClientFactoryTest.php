<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus\Client;

use App\Infrastructure\Collect\Bakus\Stream\WebSocketClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WebSocket\Client;
use WebSocket\Middleware\MiddlewareInterface;

#[CoversClass(WebSocketClientFactory::class)]
class WebSocketClientFactoryTest extends TestCase
{
    private WebSocketClientFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new WebSocketClientFactory();
    }

    public function testSetTimeoutWithValidValue(): void
    {
        $timeout = 30;
        $result = $this->factory->setTimeout($timeout);

        $this->assertSame($this->factory, $result, 'setTimeout should return self for fluent interface');
    }

    public function testSetTimeoutWithFloatValue(): void
    {
        $timeout = 30.5;
        $result = $this->factory->setTimeout($timeout);

        $this->assertSame($this->factory, $result, 'setTimeout should accept float values');
    }

    public function testSetTimeoutWithZeroValue(): void
    {
        $timeout = 0;
        $result = $this->factory->setTimeout($timeout);

        $this->assertSame($this->factory, $result, 'setTimeout should accept zero value');
    }

    #[DataProvider('invalidTimeoutProvider')]
    public function testSetTimeoutWithInvalidValueThrowsException(int|float $timeout): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid timeout '{$timeout}' provided");

        $this->factory->setTimeout($timeout);
    }

    /**
     * @return array<string, array<int|float>>
     */
    public static function invalidTimeoutProvider(): array
    {
        return [
            'negative integer' => [-1],
            'negative float' => [-0.1],
            'large negative' => [-100],
        ];
    }

    public function testSetLogger(): void
    {
        $logger = $this->createStub(LoggerInterface::class);

        $result = $this->factory->setLogger($logger);

        $this->assertSame($this->factory, $result, 'setLogger should return self for fluent interface');
    }

    public function testAddHeader(): void
    {
        $headerName = 'Authorization';
        $headerValue = 'Bearer token123';

        $result = $this->factory->addHeader($headerName, $headerValue);

        $this->assertSame($this->factory, $result, 'addHeader should return self for fluent interface');
    }

    public function testAddMiddlewares(): void
    {
        $middleware = $this->createStub(MiddlewareInterface::class);

        $result = $this->factory->addMiddlewares([$middleware]);

        $this->assertSame($this->factory, $result, 'addMiddleware should return self for fluent interface');
    }

    public function testInvokeCreatesClientWithDefaultSettings(): void
    {
        $uri = 'ws://localhost:8080/test';

        $client = ($this->factory)($uri);

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testInvokeCreatesClientWithCustomTimeout(): void
    {
        $uri = 'ws://localhost:8080/test';
        $timeout = 30;

        $this->factory->setTimeout($timeout);
        $client = ($this->factory)($uri);

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testInvokeCreatesClientWithLogger(): void
    {
        $uri = 'ws://localhost:8080/test';
        $logger = $this->createStub(LoggerInterface::class);

        $this->factory->setLogger($logger);
        $client = ($this->factory)($uri);

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testInvokeCreatesClientWithHeaders(): void
    {
        $uri = 'ws://localhost:8080/test';

        $this->factory
            ->addHeader('Authorization', 'Bearer token123')
            ->addHeader('X-Custom-Header', 'custom-value');

        $client = ($this->factory)($uri);

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testInvokeCreatesClientWithMiddlewares(): void
    {
        $uri = 'ws://localhost:8080/test';
        $middleware1 = $this->createStub(MiddlewareInterface::class);
        $middleware2 = $this->createStub(MiddlewareInterface::class);

        $this->factory
            ->addMiddlewares([$middleware1, $middleware2]);

        $client = ($this->factory)($uri);

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testInvokeCreatesClientWithAllConfiguration(): void
    {
        $uri = 'ws://localhost:8080/test';
        $timeout = 45;
        $logger = $this->createStub(LoggerInterface::class);
        $middleware = $this->createStub(MiddlewareInterface::class);

        $this->factory
            ->setTimeout($timeout)
            ->setLogger($logger)
            ->addHeader('Authorization', 'Bearer token123')
            ->addHeader('X-API-Key', 'api-key-456')
            ->addMiddlewares([$middleware]);

        $client = ($this->factory)($uri);

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testFluentInterface(): void
    {
        $middleware = $this->createStub(MiddlewareInterface::class);

        $result = $this->factory
            ->setTimeout(30)
            ->setLogger(new NullLogger())
            ->addHeader('Authorization', 'Bearer token')
            ->addHeader('X-Custom', 'value')
            ->addMiddlewares([$middleware]);

        $this->assertSame($this->factory, $result, 'All methods should return self for fluent interface');
    }

    public function testAddSameMiddlewareClassTwiceOverwritesPrevious(): void
    {
        $middleware1 = $this->createStub(MiddlewareInterface::class);
        $middleware2 = $this->createStub(MiddlewareInterface::class);

        $this->factory
            ->addMiddlewares([$middleware1, $middleware2]);

        $client = ($this->factory)('ws://localhost:8080/test');

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testAddMultipleHeadersWithSameNameOverwritesPrevious(): void
    {
        $headerName = 'Authorization';
        $firstValue = 'Bearer token1';
        $secondValue = 'Bearer token2';

        $this->factory
            ->addHeader($headerName, $firstValue)
            ->addHeader($headerName, $secondValue); // This should overwrite the first one

        $client = ($this->factory)('ws://localhost:8080/test');

        $this->assertInstanceOf(Client::class, $client);
    }

    #[DataProvider('validUriProvider')]
    public function testInvokeWithDifferentUris(string $uri): void
    {
        $client = ($this->factory)($uri);

        $this->assertInstanceOf(Client::class, $client);
    }

    /**
     * @return array<string, array<string>>
     */
    public static function validUriProvider(): array
    {
        return [
            'localhost with port' => ['ws://localhost:8080/test'],
            'localhost without port' => ['ws://localhost/test'],
            'secure websocket' => ['wss://example.com/secure'],
            'with query parameters' => ['ws://example.com/test?param=value'],
            'with path segments' => ['ws://example.com/api/v1/websocket'],
            'IP address' => ['ws://127.0.0.1:9000/socket'],
        ];
    }

    public function testFactoryIsReusable(): void
    {
        $uri1 = 'ws://localhost:8080/test1';
        $uri2 = 'ws://localhost:8080/test2';

        $this->factory->setTimeout(30);

        $client1 = ($this->factory)($uri1);
        $client2 = ($this->factory)($uri2);

        $this->assertInstanceOf(Client::class, $client1);
        $this->assertInstanceOf(Client::class, $client2);
        $this->assertNotSame($client1, $client2, 'Factory should create different client instances');
    }

    public function testSettingsArePreservedBetweenInvocations(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $middleware = $this->createStub(MiddlewareInterface::class);

        $this->factory
            ->setTimeout(25)
            ->setLogger($logger)
            ->addHeader('X-Test', 'value')
            ->addMiddlewares([$middleware]);

        $client1 = ($this->factory)('ws://localhost:8080/test1');
        $client2 = ($this->factory)('ws://localhost:8080/test2');

        $this->assertInstanceOf(Client::class, $client1);
        $this->assertInstanceOf(Client::class, $client2);
    }
}
