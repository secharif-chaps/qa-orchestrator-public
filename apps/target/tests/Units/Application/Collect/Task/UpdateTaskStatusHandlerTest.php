<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect\Task;

use App\Application\Collect\Task\UpdateTaskStatusAction;
use App\Application\Collect\Task\UpdateTaskStatusHandler;
use App\Domain\Actor\Actor;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\CollectTaskNotFoundException;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Collect\NullCollectTaskGateway;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Units\Infrastructure\SourceActivity\NullSourceActivityGateway;
use App\Tests\Units\Infrastructure\SourceActivity\NullSourceActivityLogger;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class UpdateTaskStatusHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private NullCollectTaskGateway $collectTaskGateway;
    private EventDispatcherInterface&Stub $eventDispatcher;
    private UpdateTaskStatusHandler $handler;

    protected function setUp(): void
    {
        $this->collectTaskGateway = new NullCollectTaskGateway();
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);

        $this->handler = new UpdateTaskStatusHandler(
            $this->collectTaskGateway,
            $this->eventDispatcher,
            new NullSourceGateway(),
            new NullSourceActivityLogger(),
            new NullSourceActivityGateway()
        );
    }

    private function createCollectTask(): CollectTask
    {
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'desc fr',
                'en' => 'desc en',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel fr',
                'en' => 'rel en',
            ]),
            $actor,
            $watchFile
        );

        $collectTask = new CollectTask(
            source: $source,
            watchFile: $watchFile,
            providerName: 'bakus',
            configuration: [
                'test' => 'config',
            ]
        );

        $this->forcePropertyValue($collectTask, 'test-task-id');

        return $collectTask;
    }

    public function testUpdateTaskStatusToCompleted(): void
    {
        $collectTask = $this->createCollectTask();
        $collectTask->start('provider-123', $this->eventDispatcher);
        $collectTask->resume($this->eventDispatcher);
        $this->collectTaskGateway->save($collectTask);

        $resultData = [
            'items' => [1, 2, 3],
            'total' => 3,
        ];
        $action = new UpdateTaskStatusAction(collectTaskId: 'test-task-id', status: CollectTaskStatus::COMPLETED);

        $this->handler->__invoke($action);

        $updatedTask = $this->collectTaskGateway->get('test-task-id');
        $this->assertEquals(CollectTaskStatus::COMPLETED, $updatedTask->getStatus());
        $this->assertTrue($updatedTask->isCompleted());
        $this->assertNotNull($updatedTask->getCompletedAt());
    }

    public function testUpdateTaskStatusToFailed(): void
    {
        $collectTask = $this->createCollectTask();
        $collectTask->start('provider-123', $this->eventDispatcher);
        $collectTask->resume($this->eventDispatcher);
        $this->collectTaskGateway->save($collectTask);

        $errorData = [
            'error_message' => 'Network timeout',
            'retry_count' => 3,
        ];
        $action = new UpdateTaskStatusAction(collectTaskId: 'test-task-id', status: CollectTaskStatus::FAILED);

        $this->handler->__invoke($action);

        $updatedTask = $this->collectTaskGateway->get('test-task-id');
        $this->assertEquals(CollectTaskStatus::FAILED, $updatedTask->getStatus());
        $this->assertTrue($updatedTask->isFailed());
        $this->assertNotNull($updatedTask->getCompletedAt());
    }

    public function testUpdateTaskStatusToCancelled(): void
    {
        $collectTask = $this->createCollectTask();
        $collectTask->start('provider-123', $this->eventDispatcher);
        $this->collectTaskGateway->save($collectTask);

        $action = new UpdateTaskStatusAction(collectTaskId: 'test-task-id', status: CollectTaskStatus::CANCELLED);

        $this->handler->__invoke($action);

        $updatedTask = $this->collectTaskGateway->get('test-task-id');
        $this->assertEquals(CollectTaskStatus::CANCELLED, $updatedTask->getStatus());
        $this->assertTrue($updatedTask->isCancelled());
        $this->assertNotNull($updatedTask->getCompletedAt());
    }

    public function testUpdateTaskStatusToQueued(): void
    {
        $collectTask = $this->createCollectTask();
        $this->collectTaskGateway->save($collectTask);

        $action = new UpdateTaskStatusAction(collectTaskId: 'test-task-id', status: CollectTaskStatus::QUEUED);

        $this->handler->__invoke($action);

        $updatedTask = $this->collectTaskGateway->get('test-task-id');
        $this->assertEquals(CollectTaskStatus::QUEUED, $updatedTask->getStatus());
        $this->assertTrue($updatedTask->isActive());
    }

    public function testUpdateTaskStatusToRunning(): void
    {
        $collectTask = $this->createCollectTask();
        $collectTask->start('provider-123', $this->eventDispatcher);
        $this->collectTaskGateway->save($collectTask);

        $action = new UpdateTaskStatusAction(collectTaskId: 'test-task-id', status: CollectTaskStatus::RUNNING);

        $this->handler->__invoke($action);

        $updatedTask = $this->collectTaskGateway->get('test-task-id');
        $this->assertEquals(CollectTaskStatus::RUNNING, $updatedTask->getStatus());
        $this->assertTrue($updatedTask->isActive());
    }

    public function testUpdateTaskStatusWithNonExistentTask(): void
    {
        $action = new UpdateTaskStatusAction(
            collectTaskId: 'non-existent-id',
            status: CollectTaskStatus::COMPLETED
        );

        $this->expectException(CollectTaskNotFoundException::class);

        $this->handler->__invoke($action);
    }

    public function testUpdateTaskStatusWithResult(): void
    {
        $collectTask = $this->createCollectTask();
        $collectTask->start('provider-123', $this->eventDispatcher);
        $this->collectTaskGateway->save($collectTask);

        $resultData = [
            'progress' => 50,
            'items_processed' => 25,
        ];
        $action = new UpdateTaskStatusAction(collectTaskId: 'test-task-id', status: CollectTaskStatus::RUNNING);

        $this->handler->__invoke($action);

        $updatedTask = $this->collectTaskGateway->get('test-task-id');
        $this->assertEquals(CollectTaskStatus::RUNNING, $updatedTask->getStatus());
        $this->assertTrue($updatedTask->isActive());
    }
}
