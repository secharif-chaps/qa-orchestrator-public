<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus\Stream\Middleware;

use App\Application\Collect\Auth\ValidateCollectTaskTokenAction;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\InvalidCollectTaskTokenException;
use App\Domain\Collect\ProviderGatewayLocatorInterface;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Collect\Bakus\Stream\Middleware\CollectTaskAuthorizationMiddleware;
use App\Tests\Units\Infrastructure\Collect\NullProviderGateway;
use App\Tests\Units\Infrastructure\Collect\NullProviderGatewayLocator;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CollectTaskAuthorizationMiddlewareTest extends TestCase
{
    use EntityUtilsTrait;
    private FakeWebSocketConnection $connection;
    private FakeProcessHttpStack $stack;

    protected function setUp(): void
    {
        $this->connection = new FakeWebSocketConnection();
        $this->stack = new FakeProcessHttpStack();
    }

    private function createMiddleware(
        \Closure $handler,
        ?ProviderGatewayLocatorInterface $providerLocator = null,
    ): CollectTaskAuthorizationMiddleware {
        return new CollectTaskAuthorizationMiddleware(
            new NullMessageBus($handler),
            new NullProviderGateway(),
            $providerLocator ?? new NullProviderGatewayLocator(),
            new NullLogger()
        );
    }

    public function testAuthorizationSuccessWithValidToken(): void
    {
        $collectTask = $this->createCollectTask('task-123', 'provider-456', CollectTaskStatus::RUNNING);

        $middleware = $this->createMiddleware(function (object $action) use ($collectTask) {
            if ($action instanceof ValidateCollectTaskTokenAction) {
                return $collectTask;
            }

            throw new \RuntimeException('Unexpected action');
        });

        $request = new FakeServerRequest([
            'Authorization' => 'Bearer valid-token-here',
        ]);
        $this->stack->setRequestToReturn($request);

        $result = $middleware->processHttpIncoming($this->stack, $this->connection);

        $this->assertSame($request, $result);
        $this->assertFalse($this->connection->isClosed());
        $this->assertEquals('task-123', $this->connection->getMeta('collect_task_id'));
        $this->assertEquals('provider-456', $this->connection->getMeta('provider_task_id'));
    }

    public function testAuthorizationFailsWhenNoAuthorizationHeaderAndNoCollectTaskId(): void
    {
        $middleware = $this->createMiddleware(fn () => null);

        $request = new FakeServerRequest([]);
        $this->stack->setRequestToReturn($request);

        $result = $middleware->processHttpIncoming($this->stack, $this->connection);

        $this->assertTrue($this->connection->isRejected());
        $response = $this->connection->getHandshakeResponse();
        $this->assertNotNull($response);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Unauthorized: Missing Authorization header.', (string) $response->getBody());
    }

    public function testAuthorizationSucceedsWhenCollectTaskIdAlreadyInMeta(): void
    {
        // Pre-set collect_task_id in connection metadata
        $this->connection->setMeta('collect_task_id', 'existing-task-123');

        $middleware = $this->createMiddleware(fn () => null);

        $request = new FakeServerRequest([]); // No Authorization header
        $this->stack->setRequestToReturn($request);

        $result = $middleware->processHttpIncoming($this->stack, $this->connection);

        $this->assertSame($request, $result);
        $this->assertFalse($this->connection->isClosed());
        $this->assertEquals('existing-task-123', $this->connection->getMeta('collect_task_id'));
    }

    public function testAuthorizationFailsWithInvalidAuthorizationHeaderFormat(): void
    {
        $middleware = $this->createMiddleware(fn () => null);

        $request = new FakeServerRequest([
            'Authorization' => 'Basic invalid-format',
        ]);
        $this->stack->setRequestToReturn($request);

        $result = $middleware->processHttpIncoming($this->stack, $this->connection);

        $this->assertTrue($this->connection->isRejected());
        $response = $this->connection->getHandshakeResponse();
        $this->assertNotNull($response);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Unauthorized: Invalid Authorization header format.', (string) $response->getBody());
    }

    public function testAuthorizationFailsWithInvalidToken(): void
    {
        $middleware = $this->createMiddleware(function (object $action) {
            if ($action instanceof ValidateCollectTaskTokenAction) {
                throw InvalidCollectTaskTokenException::fromDecodingError(
                    new \UnexpectedValueException('Invalid token')
                );
            }

            throw new \RuntimeException('Unexpected action');
        });

        $request = new FakeServerRequest([
            'Authorization' => 'Bearer invalid-token',
        ]);
        $this->stack->setRequestToReturn($request);

        $result = $middleware->processHttpIncoming($this->stack, $this->connection);

        $this->assertTrue($this->connection->isRejected());
        $response = $this->connection->getHandshakeResponse();
        $this->assertNotNull($response);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString(
            'Unauthorized: Invalid collect task token: "Invalid token"',
            (string) $response->getBody()
        );
    }

    public function testAuthorizationFailsWhenCollectTaskIsNotActive(): void
    {
        $collectTask = $this->createCollectTask('task-123', 'provider-456', CollectTaskStatus::CANCELLED);

        $middleware = $this->createMiddleware(function (object $action) use ($collectTask) {
            if ($action instanceof ValidateCollectTaskTokenAction) {
                return $collectTask;
            }

            throw new \RuntimeException('Unexpected action');
        });

        $request = new FakeServerRequest([
            'Authorization' => 'Bearer valid-token',
        ]);
        $this->stack->setRequestToReturn($request);

        $result = $middleware->processHttpIncoming($this->stack, $this->connection);

        $this->assertTrue($this->connection->isRejected());
        $response = $this->connection->getHandshakeResponse();
        $this->assertNotNull($response);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Unauthorized: Collect task is not active.', (string) $response->getBody());
    }

    public function testAuthorizationHandlesAllInactiveCollectTaskStatuses(): void
    {
        $inactiveStatuses = [
            CollectTaskStatus::CANCELLED,
            CollectTaskStatus::FAILED,
            CollectTaskStatus::COMPLETED,
        ];

        foreach ($inactiveStatuses as $status) {
            $this->connection->reset();

            $collectTask = $this->createCollectTask('task-123', 'provider-456', $status);

            $middleware = $this->createMiddleware(function (object $action) use ($collectTask) {
                if ($action instanceof ValidateCollectTaskTokenAction) {
                    return $collectTask;
                }

                throw new \RuntimeException('Unexpected action');
            });

            $request = new FakeServerRequest([
                'Authorization' => 'Bearer valid-token',
            ]);
            $this->stack->setRequestToReturn($request);

            $result = $middleware->processHttpIncoming($this->stack, $this->connection);

            $this->assertTrue(
                $this->connection->isRejected(),
                "Connection should be rejected for status: {$status->value}"
            );
            $response = $this->connection->getHandshakeResponse();
            $this->assertNotNull($response);
            $this->assertEquals(401, $response->getStatusCode());
            $this->assertEquals('Unauthorized: Collect task is not active.', (string) $response->getBody());
        }
    }

    public function testAuthorizationSucceedsWithAllActiveCollectTaskStatuses(): void
    {
        $activeStatuses = [CollectTaskStatus::QUEUED, CollectTaskStatus::RUNNING];

        foreach ($activeStatuses as $status) {
            $this->connection->reset();

            $collectTask = $this->createCollectTask('task-123', 'provider-456', $status);

            $middleware = $this->createMiddleware(function (object $action) use ($collectTask) {
                if ($action instanceof ValidateCollectTaskTokenAction) {
                    return $collectTask;
                }

                throw new \RuntimeException('Unexpected action');
            });

            $request = new FakeServerRequest([
                'Authorization' => 'Bearer valid-token',
            ]);
            $this->stack->setRequestToReturn($request);

            $result = $middleware->processHttpIncoming($this->stack, $this->connection);

            $this->assertFalse(
                $this->connection->isClosed(),
                "Connection should not be closed for active status: {$status->value}"
            );
            $this->assertEquals('task-123', $this->connection->getMeta('collect_task_id'));
            $this->assertEquals('provider-456', $this->connection->getMeta('provider_task_id'));
        }
    }

    public function testAuthorizationExtractsBearerTokenCorrectly(): void
    {
        $expectedToken = 'my-jwt-token-here';
        $actualToken = null;

        $middleware = $this->createMiddleware(function (object $action) use (&$actualToken) {
            if ($action instanceof ValidateCollectTaskTokenAction) {
                $actualToken = $action->collectTaskToken;

                return $this->createCollectTask('task-123', 'provider-456', CollectTaskStatus::RUNNING);
            }

            throw new \RuntimeException('Unexpected action');
        });

        $request = new FakeServerRequest([
            'Authorization' => "Bearer {$expectedToken}",
        ]);
        $this->stack->setRequestToReturn($request);

        $middleware->processHttpIncoming($this->stack, $this->connection);

        $this->assertEquals($expectedToken, $actualToken);
    }

    public function testAuthorizationSetsMetadataOnConnection(): void
    {
        $collectTask = $this->createCollectTask('task-999', 'provider-888', CollectTaskStatus::RUNNING);

        $middleware = $this->createMiddleware(function (object $action) use ($collectTask) {
            if ($action instanceof ValidateCollectTaskTokenAction) {
                return $collectTask;
            }

            throw new \RuntimeException('Unexpected action');
        });

        $request = new FakeServerRequest([
            'Authorization' => 'Bearer valid-token',
        ]);
        $this->stack->setRequestToReturn($request);

        $middleware->processHttpIncoming($this->stack, $this->connection);

        $this->assertEquals('task-999', $this->connection->getMeta('collect_task_id'));
        $this->assertEquals('provider-888', $this->connection->getMeta('provider_task_id'));
    }

    public function testAuthorizationFailsWithUnknownProviderName(): void
    {
        $collectTask = $this->createCollectTask(
            'task-123',
            'provider-456',
            CollectTaskStatus::RUNNING,
            'unknown_provider'
        );

        $middleware = $this->createMiddleware(function (object $action) use ($collectTask) {
            if ($action instanceof ValidateCollectTaskTokenAction) {
                return $collectTask;
            }

            throw new \RuntimeException('Unexpected action');
        });

        $request = new FakeServerRequest([
            'Authorization' => 'Bearer valid-token',
        ]);
        $this->stack->setRequestToReturn($request);

        $result = $middleware->processHttpIncoming($this->stack, $this->connection);

        $this->assertTrue($this->connection->isRejected());
        $response = $this->connection->getHandshakeResponse();
        $this->assertNotNull($response);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Unknown collect provider "unknown_provider"', (string) $response->getBody());
    }

    public function testAuthorizationFailsWithEmptyProviderName(): void
    {
        $collectTask = $this->createCollectTask('task-123', 'provider-456', CollectTaskStatus::RUNNING, '');

        $middleware = $this->createMiddleware(function (object $action) use ($collectTask) {
            if ($action instanceof ValidateCollectTaskTokenAction) {
                return $collectTask;
            }

            throw new \RuntimeException('Unexpected action');
        });

        $request = new FakeServerRequest([
            'Authorization' => 'Bearer valid-token',
        ]);
        $this->stack->setRequestToReturn($request);

        $result = $middleware->processHttpIncoming($this->stack, $this->connection);

        $this->assertTrue($this->connection->isRejected());
        $response = $this->connection->getHandshakeResponse();
        $this->assertNotNull($response);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Unknown collect provider', (string) $response->getBody());
    }

    public function testAuthorizationSucceedsWithKnownProviderName(): void
    {
        $collectTask = $this->createCollectTask('task-123', 'provider-456', CollectTaskStatus::RUNNING, 'bakus');

        $middleware = $this->createMiddleware(function (object $action) use ($collectTask) {
            if ($action instanceof ValidateCollectTaskTokenAction) {
                return $collectTask;
            }

            throw new \RuntimeException('Unexpected action');
        });

        $request = new FakeServerRequest([
            'Authorization' => 'Bearer valid-token',
        ]);
        $this->stack->setRequestToReturn($request);

        $result = $middleware->processHttpIncoming($this->stack, $this->connection);

        $this->assertSame($request, $result);
        $this->assertFalse($this->connection->isClosed());
        $this->assertEquals('task-123', $this->connection->getMeta('collect_task_id'));
    }

    private function createCollectTask(
        string $id,
        string $providerTaskId,
        CollectTaskStatus $status,
        string $providerName = 'bakus',
    ): CollectTask {
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $source = new Source(
            'source-name',
            TranslatedText::fromArray([
                'fr' => 'Description en français',
                'en' => 'Description in English',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'Pertinence en français',
                'en' => 'Relevance in English',
            ]),
            null,
            $watchFile,
        );
        $this->forcePropertyValue($source, 'source-id');

        $collectTask = new CollectTask($source, $watchFile, $providerName, [], null, $status);
        $this->forcePropertyValue($collectTask, $id);

        // Set provider task ID using reflection
        $reflection = new \ReflectionClass($collectTask);
        $property = $reflection->getProperty('providerTaskId');
        $property->setValue($collectTask, $providerTaskId);

        return $collectTask;
    }
}
