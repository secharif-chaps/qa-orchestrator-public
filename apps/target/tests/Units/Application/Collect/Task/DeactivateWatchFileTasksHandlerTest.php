<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect\Task;

use App\Application\Collect\Task\DeactivateWatchFileTasksAction;
use App\Application\Collect\Task\DeactivateWatchFileTasksHandler;
use App\Domain\Actor\Actor;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\ProviderGatewayInterface;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Collect\NullCollectTaskGateway;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

final class DeactivateWatchFileTasksHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private DeactivateWatchFileTasksHandler $handler;
    private NullCollectTaskGateway $collectTaskGateway;
    private ProviderGatewayInterface&MockObject $providerGateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->providerGateway = $this->createMock(ProviderGatewayInterface::class);
        $this->collectTaskGateway = new NullCollectTaskGateway();

        $this->handler = new DeactivateWatchFileTasksHandler(
            $this->providerGateway,
            $this->collectTaskGateway,
            new NullSourceGateway(),
            new NullLogger(),
        );
    }

    public function testInvokeCancelsActiveTasks(): void
    {
        $watchFile = new WatchFile('Test Watchfile Name', 'Test User Objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, (string) Uuid::v4());

        $source1 = new Source(
            name: 'Test Source 1',
            description: new TranslatedText('Test Description 1', 'Test Description 1'),
            type: SourceType::WEBSITE,
            url: 'https://example.com/1',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('High', 'High'),
            actor: new Actor('Test Actor 1', new Organisation('Test Org', 'test-org-id')),
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($source1, (string) Uuid::v4());

        $task1 = new CollectTask(
            $source1,
            $watchFile,
            'test-provider',
            providerTaskId: 'provider-task-1',
            status: CollectTaskStatus::RUNNING,
        );
        $this->forcePropertyValue($task1, (string) Uuid::v4());
        $this->collectTaskGateway->save($task1);

        $source2 = new Source(
            name: 'Test Source 2',
            description: new TranslatedText('Test Description 2', 'Test Description 2'),
            type: SourceType::WEBSITE,
            url: 'http://example.com/2',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('Medium', 'Medium'),
            actor: new Actor('Test Actor 2', new Organisation('Test Org', 'test-org-id')),
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($source2, (string) Uuid::v4());

        $task2 = new CollectTask(
            $source2,
            $watchFile,
            'test-provider',
            providerTaskId: 'provider-task-2',
            status: CollectTaskStatus::QUEUED,
        );
        $this->forcePropertyValue($task2, (string) Uuid::v4());
        $this->collectTaskGateway->save($task2);

        $this->providerGateway
            ->expects(self::exactly(2))
            ->method('cancelTask')
            ->with(self::logicalOr('provider-task-1', 'provider-task-2'));

        ($this->handler)(new DeactivateWatchFileTasksAction($watchFile->getId()));

        self::assertSame(CollectTaskStatus::CANCELLED, $task1->getStatus());
        self::assertSame(CollectTaskStatus::CANCELLED, $task2->getStatus());
    }

    public function testInvokeSkipsNonCancellableTasks(): void
    {
        $watchFile = new WatchFile('Test Watchfile Name', 'Test User Objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, (string) Uuid::v4());

        $source = new Source(
            name: 'Test Source',
            description: new TranslatedText('Test Description', 'Test Description'),
            type: SourceType::WEBSITE,
            url: 'http://example.com/3',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('Low', 'Low'),
            actor: new Actor('Test Actor 3', new Organisation('Test Org', 'test-org-id')),
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($source, (string) Uuid::v4());

        $task = new CollectTask(
            $source,
            $watchFile,
            'test-provider',
            providerTaskId: 'provider-task-1',
            status: CollectTaskStatus::COMPLETED,
        );
        $this->collectTaskGateway->save($task);

        $this->providerGateway->expects(self::never())->method('cancelTask');

        ($this->handler)(new DeactivateWatchFileTasksAction($watchFile->getId()));

        self::assertSame(CollectTaskStatus::COMPLETED, $task->getStatus());
    }

    public function testInvokeSkipsTasksWithoutProviderId(): void
    {
        $watchFile = new WatchFile('Test Watchfile Name', 'Test User Objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, (string) Uuid::v4());

        $source = new Source(
            name: 'Test Source',
            description: new TranslatedText('Test Description', 'Test Description'),
            type: SourceType::WEBSITE,
            url: 'http://example.com/4',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('None', 'None'),
            actor: new Actor('Test Actor 4', new Organisation('Test Org', 'test-org-id')),
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($source, (string) Uuid::v4());

        $task = new CollectTask($source, $watchFile, 'test-provider', status: CollectTaskStatus::QUEUED);
        $this->collectTaskGateway->save($task);

        $this->providerGateway->expects(self::never())->method('cancelTask');

        ($this->handler)(new DeactivateWatchFileTasksAction($watchFile->getId()));

        // Status remains QUEUED because it was never cancelled
        self::assertSame(CollectTaskStatus::QUEUED, $task->getStatus());
    }

    public function testInvokeDoesNothingWhenNoActiveTasks(): void
    {
        $watchFileId = (string) Uuid::v4();

        $this->providerGateway->expects(self::never())->method('cancelTask');

        ($this->handler)(new DeactivateWatchFileTasksAction($watchFileId));

        self::assertCount(0, $this->collectTaskGateway->getAll());
    }

    public function testInvokeHandles404ErrorGracefully(): void
    {
        $watchFile = new WatchFile('Test Watchfile Name', 'Test User Objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, (string) Uuid::v4());

        $source = new Source(
            name: 'Test Source',
            description: new TranslatedText('Test Description', 'Test Description'),
            type: SourceType::WEBSITE,
            url: 'https://example.com/5',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('High', 'High'),
            actor: new Actor('Test Actor 5', new Organisation('Test Org', 'test-org-id')),
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($source, (string) Uuid::v4());

        $task = new CollectTask(
            $source,
            $watchFile,
            'test-provider',
            providerTaskId: 'provider-task-404',
            status: CollectTaskStatus::QUEUED,
        );
        $this->forcePropertyValue($task, (string) Uuid::v4());
        $this->collectTaskGateway->save($task);

        // Simulate 404 response from Bakus
        $response = $this->createStub(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->method('getStatusCode')
->willReturn(404);

        $exception = new \App\Domain\Collect\Exception\CollectHttpException(
            $response,
            'Task not found on provider',
        );

        $this->providerGateway
            ->expects(self::once())
            ->method('cancelTask')
            ->with('provider-task-404')
            ->willThrowException($exception);

        ($this->handler)(new DeactivateWatchFileTasksAction($watchFile->getId()));

        // Task should still be marked as CANCELLED locally (idempotent cleanup)
        self::assertSame(CollectTaskStatus::CANCELLED, $task->getStatus());
    }

    public function testInvokeContinuesWithOtherTasksOnPartialFailure(): void
    {
        $watchFile = new WatchFile('Test Watchfile Name', 'Test User Objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, (string) Uuid::v4());

        $source1 = new Source(
            name: 'Test Source 1',
            description: new TranslatedText('Test Description 1', 'Test Description 1'),
            type: SourceType::WEBSITE,
            url: 'https://example.com/6',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('High', 'High'),
            actor: new Actor('Test Actor 6', new Organisation('Test Org', 'test-org-id')),
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($source1, (string) Uuid::v4());

        $task1 = new CollectTask(
            $source1,
            $watchFile,
            'test-provider',
            providerTaskId: 'provider-task-1',
            status: CollectTaskStatus::QUEUED,
        );
        $this->forcePropertyValue($task1, (string) Uuid::v4());
        $this->collectTaskGateway->save($task1);

        $source2 = new Source(
            name: 'Test Source 2',
            description: new TranslatedText('Test Description 2', 'Test Description 2'),
            type: SourceType::WEBSITE,
            url: 'http://example.com/7',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('Medium', 'Medium'),
            actor: new Actor('Test Actor 7', new Organisation('Test Org', 'test-org-id')),
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($source2, (string) Uuid::v4());

        $task2 = new CollectTask(
            $source2,
            $watchFile,
            'test-provider',
            providerTaskId: 'provider-task-2',
            status: CollectTaskStatus::QUEUED,
        );
        $this->forcePropertyValue($task2, (string) Uuid::v4());
        $this->collectTaskGateway->save($task2);

        $source3 = new Source(
            name: 'Test Source 3',
            description: new TranslatedText('Test Description 3', 'Test Description 3'),
            type: SourceType::WEBSITE,
            url: 'http://example.com/8',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('Low', 'Low'),
            actor: new Actor('Test Actor 8', new Organisation('Test Org', 'test-org-id')),
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($source3, (string) Uuid::v4());

        $task3 = new CollectTask(
            $source3,
            $watchFile,
            'test-provider',
            providerTaskId: 'provider-task-3',
            status: CollectTaskStatus::QUEUED,
        );
        $this->forcePropertyValue($task3, (string) Uuid::v4());
        $this->collectTaskGateway->save($task3);

        // Simulate failure on 2nd task (500 error, no retry)
        $response = $this->createStub(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->method('getStatusCode')
->willReturn(500);

        $exception = new \App\Domain\Collect\Exception\CollectHttpException($response, 'Internal Server Error');

        $this->providerGateway
            ->expects(self::exactly(3)) // 1 attempt per task (no retry)
            ->method('cancelTask')
            ->willReturnCallback(function ($taskId) use ($exception) {
                if ('provider-task-2' === $taskId) {
                    throw $exception;
                }
            });

        ($this->handler)(new DeactivateWatchFileTasksAction($watchFile->getId()));

        // Task 1 and 3 should be cancelled successfully
        self::assertSame(CollectTaskStatus::CANCELLED, $task1->getStatus());
        self::assertSame(CollectTaskStatus::CANCELLED, $task3->getStatus());

        // Task 2 should remain QUEUED because cancellation failed (no retry)
        self::assertSame(CollectTaskStatus::QUEUED, $task2->getStatus());
    }
}
