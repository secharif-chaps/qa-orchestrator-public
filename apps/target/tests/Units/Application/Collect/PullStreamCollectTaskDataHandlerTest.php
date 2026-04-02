<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect;

use App\Application\Collect\PullStreamCollectTaskDataAction;
use App\Application\Collect\PullStreamCollectTaskDataHandler;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\CollectTaskNotFoundException;
use App\Domain\Collect\Stream\Exception\CollectDataStreamException;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Collect\NullCollectTaskGateway;
use App\Tests\Units\Infrastructure\Collect\NullProviderGateway;
use App\Tests\Units\Infrastructure\Collect\Stream\NullWebSocketConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(PullStreamCollectTaskDataAction::class)]
#[CoversClass(PullStreamCollectTaskDataHandler::class)]
class PullStreamCollectTaskDataHandlerTest extends TestCase
{
    private PullStreamCollectTaskDataHandler $handler;
    private NullCollectTaskGateway $collectTaskGateway;
    private NullProviderGateway $providerGateway;
    private NullWebSocketConnection $webSocketConnection;

    protected function setUp(): void
    {
        $this->collectTaskGateway = new NullCollectTaskGateway();
        $this->providerGateway = new NullProviderGateway();
        $this->webSocketConnection = new NullWebSocketConnection();

        $this->handler = new PullStreamCollectTaskDataHandler(
            $this->collectTaskGateway,
            $this->providerGateway,
            $this->webSocketConnection,
            new NullLogger()
        );
    }

    public function testConnectToQuerySuccessfully(): void
    {
        $collectTask = $this->createCollectTask('collect-task-1', 'provider-task-1', CollectTaskStatus::RUNNING);
        $this->collectTaskGateway->save($collectTask);
        $this->providerGateway->setTaskStatus('provider-task-1', CollectTaskStatus::RUNNING);

        $action = new PullStreamCollectTaskDataAction('collect-task-1');

        $result = ($this->handler)($action);

        $this->assertTrue($result);
        $connectionCalls = $this->webSocketConnection->getConnectionCalls();
        $this->assertCount(1, $connectionCalls);
        $this->assertEquals('collect-task-1', $connectionCalls[0]['collectTaskId']);
        $this->assertEquals('provider-task-1', $connectionCalls[0]['queryId']);
        $this->assertEquals(1, $this->webSocketConnection->getDisconnectCallCount());
    }

    public function testConnectToQueryFailsWhenCollectTaskNotActive(): void
    {
        // Arrange
        $collectTask = $this->createCollectTask('collect-task-1', 'provider-task-1', CollectTaskStatus::CANCELLED);
        $this->collectTaskGateway->save($collectTask);

        $action = new PullStreamCollectTaskDataAction('collect-task-1');

        // Act & Assert
        $this->expectException(CollectDataStreamException::class);
        $this->expectExceptionMessage('Collect task collect-task-1 is not active (status: cancelled)');

        ($this->handler)($action);
    }

    public function testConnectToQueryFailsWhenCollectTaskNotFound(): void
    {
        $action = new PullStreamCollectTaskDataAction('non-existent-task');

        $this->expectException(CollectTaskNotFoundException::class);

        ($this->handler)($action);
    }

    public function testConnectToQueryFailsWhenProviderTaskIdIsNull(): void
    {
        $collectTask = $this->createCollectTask('collect-task-1', null, CollectTaskStatus::RUNNING);
        $this->collectTaskGateway->save($collectTask);

        $action = new PullStreamCollectTaskDataAction('collect-task-1');

        $this->expectException(CollectDataStreamException::class);
        $this->expectExceptionMessage('Collect task collect-task-1 has no provider task ID');

        ($this->handler)($action);
    }

    public function testConnectToQueryFailsWhenProviderTaskNotActive(): void
    {
        $collectTask = $this->createCollectTask('collect-task-1', 'provider-task-1', CollectTaskStatus::RUNNING);
        $this->collectTaskGateway->save($collectTask);
        $this->providerGateway->setTaskStatus('provider-task-1', CollectTaskStatus::CANCELLED);

        $action = new PullStreamCollectTaskDataAction('collect-task-1');

        $this->expectException(CollectDataStreamException::class);
        $this->expectExceptionMessage('Provider task provider-task-1 is not active (status: cancelled)');

        ($this->handler)($action);
    }

    public function testConnectToQueryFailsWhenWebSocketConnectionFails(): void
    {
        $collectTask = $this->createCollectTask('collect-task-1', 'provider-task-1', CollectTaskStatus::RUNNING);
        $this->collectTaskGateway->save($collectTask);
        $this->providerGateway->setTaskStatus('provider-task-1', CollectTaskStatus::RUNNING);
        $this->webSocketConnection->throwExceptionOnConnect('WebSocket connection failed');

        $action = new PullStreamCollectTaskDataAction('collect-task-1');

        $this->expectException(CollectDataStreamException::class);
        $this->expectExceptionMessage('WebSocket connection failed');

        try {
            ($this->handler)($action);
        } finally {
            // Ensure disconnect is always called
            $this->assertEquals(1, $this->webSocketConnection->getDisconnectCallCount());
        }
    }

    public function testConnectToQueryWrapsGenericExceptions(): void
    {
        $collectTask = $this->createCollectTask('collect-task-1', 'provider-task-1', CollectTaskStatus::RUNNING);
        $this->collectTaskGateway->save($collectTask);
        $this->providerGateway->setTaskStatus('provider-task-1', CollectTaskStatus::RUNNING);
        $this->webSocketConnection->throwGenericExceptionOnConnect('Generic runtime error');

        $action = new PullStreamCollectTaskDataAction('collect-task-1');

        $this->expectException(CollectDataStreamException::class);
        $this->expectExceptionMessage('Generic runtime error');

        try {
            ($this->handler)($action);
        } finally {
            // Ensure disconnect is always called
            $this->assertEquals(1, $this->webSocketConnection->getDisconnectCallCount());
        }
    }

    public function testDisconnectIsAlwaysCalledInFinallyBlock(): void
    {
        $collectTask = $this->createCollectTask('collect-task-1', 'provider-task-1', CollectTaskStatus::RUNNING);
        $this->collectTaskGateway->save($collectTask);
        $this->providerGateway->setTaskStatus('provider-task-1', CollectTaskStatus::RUNNING);
        $this->webSocketConnection->throwExceptionOnConnect('Test exception');

        $action = new PullStreamCollectTaskDataAction('collect-task-1');
        $this->expectException(CollectDataStreamException::class);

        ($this->handler)($action);

        $this->assertEquals(1, $this->webSocketConnection->getDisconnectCallCount());
    }

    public function testConnectToQueryHandlesAllCollectTaskStatuses(): void
    {
        $inactiveStatuses = [
            CollectTaskStatus::CANCELLED,
            CollectTaskStatus::FAILED,
            CollectTaskStatus::COMPLETED,
        ];

        foreach ($inactiveStatuses as $index => $status) {
            // Reset for each test
            $this->collectTaskGateway->clear();
            $this->webSocketConnection->reset();

            $taskId = 'collect-task-' . ($index + 1);
            $collectTask = $this->createCollectTask($taskId, 'provider-task-1', $status);
            $this->collectTaskGateway->save($collectTask);

            $action = new PullStreamCollectTaskDataAction($taskId);

            try {
                ($this->handler)($action);
                $this->fail("Expected exception was not thrown for status: {$status->value}");
            } catch (CollectDataStreamException $e) {
                $this->assertStringContainsString("is not active (status: {$status->value})", $e->getMessage());
                // Expected exception, continue to next iteration
                continue;
            }
        }
    }

    public function testConnectToQueryHandlesAllProviderTaskStatuses(): void
    {
        $inactiveStatuses = [
            CollectTaskStatus::CANCELLED,
            CollectTaskStatus::FAILED,
            CollectTaskStatus::COMPLETED,
        ];

        foreach ($inactiveStatuses as $index => $status) {
            // Reset for each test
            $this->collectTaskGateway->clear();
            $this->webSocketConnection->reset();

            $taskId = 'collect-task-' . ($index + 1);
            $providerTaskId = 'provider-task-' . ($index + 1);

            $collectTask = $this->createCollectTask($taskId, $providerTaskId, CollectTaskStatus::RUNNING);
            $this->collectTaskGateway->save($collectTask);
            $this->providerGateway->setTaskStatus($providerTaskId, $status);

            $action = new PullStreamCollectTaskDataAction($taskId);

            try {
                ($this->handler)($action);
                $this->fail("Expected exception was not thrown for status: {$status->value}");
            } catch (CollectDataStreamException $e) {
                $this->assertStringContainsString("is not active (status: {$status->value})", $e->getMessage());
                // Expected exception, continue to next iteration
                continue;
            }
        }
    }

    private function createCollectTask(string $id, ?string $providerTaskId, CollectTaskStatus $status): CollectTask
    {
        $user = new User('user-1', 'test@chapsvision.com', [], 'testuser');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $source = new Source(
            name: 'Test Source',
            description: new TranslatedText(fr: 'Test source description', en: 'Test source description'),
            type: SourceType::WEBSITE,
            url: 'https://chapsvision.com',
            primaryDomain: 'chapsvision.com',
            relevance: TranslatedText::fromArray([
                'fr' => 'Contenu pertinent pour le test',
                'en' => 'Relevant content for testing',
            ]),
            actor: null,
            watchFile: $watchFile
        );

        $collectTask = new CollectTask($source, $watchFile, 'test-provider', [], null, $status);

        // Set ID using reflection since it's auto-generated
        $reflection = new \ReflectionClass($collectTask);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($collectTask, $id);

        if (null !== $providerTaskId) {
            $providerTaskIdProperty = $reflection->getProperty('providerTaskId');
            $providerTaskIdProperty->setAccessible(true);
            $providerTaskIdProperty->setValue($collectTask, $providerTaskId);
        }

        return $collectTask;
    }
}
