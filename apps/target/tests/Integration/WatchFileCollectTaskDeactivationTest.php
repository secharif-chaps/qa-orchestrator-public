<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Collect\Task\DeactivateWatchFileTasksAction;
use App\Application\Collect\Task\DeactivateWatchFileTasksHandler;
use App\DataFixtures\Factory\Collect\CollectTaskFactory;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\CollectHttpException;
use App\Domain\Collect\ProviderGatewayInterface;
use App\Domain\Source\SourceStatus;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileStatus;
use Symfony\Contracts\HttpClient\ResponseInterface;

class WatchFileCollectTaskDeactivationTest extends AbstractApiTestCase
{
    public function testDeactivatingWatchFileCancelsAllCollectTasks(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create([
                'status' => WatchFileStatus::ENABLED,
                'name' => 'Test WatchFile',
            ])
        ;

        // Create 3 active sources with running tasks
        $source1 = SourceFactory::new()->create([
            'watchFile' => $watchFile,
            'name' => 'Source 1',
            'status' => SourceStatus::ACTIVE,
        ]);

        $source2 = SourceFactory::new()->create([
            'watchFile' => $watchFile,
            'name' => 'Source 2',
            'status' => SourceStatus::ACTIVE,
        ]);

        $source3 = SourceFactory::new()->create([
            'watchFile' => $watchFile,
            'name' => 'Source 3',
            'status' => SourceStatus::ACTIVE,
        ]);

        // Create CollectTasks using Foundry
        $task1 = CollectTaskFactory::createOne([
            'source' => $source1,
            'watchFile' => $watchFile,
            'providerTaskId' => 'bakus-task-1',
            'status' => CollectTaskStatus::RUNNING,
        ]);
        $task1Id = $task1->getId();

        $task2 = CollectTaskFactory::createOne([
            'source' => $source2,
            'watchFile' => $watchFile,
            'providerTaskId' => 'bakus-task-2',
            'status' => CollectTaskStatus::QUEUED,
        ]);
        $task2Id = $task2->getId();

        $task3 = CollectTaskFactory::createOne([
            'source' => $source3,
            'watchFile' => $watchFile,
            'providerTaskId' => 'bakus-task-3',
            'status' => CollectTaskStatus::RUNNING,
        ]);
        $task3Id = $task3->getId();

        // Mock the ProviderGateway to succeed on all cancellations
        $providerGateway = $this->createMock(ProviderGatewayInterface::class);
        $providerGateway
            ->expects($this->exactly(3))
            ->method('cancelTask')
            ->willReturnCallback(function ($taskId) {
                $this->assertContains($taskId, ['bakus-task-1', 'bakus-task-2', 'bakus-task-3']);
            });

        self::getContainer()->set(ProviderGatewayInterface::class, $providerGateway);

        // Deactivate the WatchFile
        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/draft");

        $this->assertResponseStatusCodeSame(201);

        // Manually trigger deferred task deactivation
        // DispatchAfterCurrentBusStamp() defers messages until after the HTTP transaction,
        // so we must manually call the handler to simulate async processing in tests
        $deactivateHandler = self::getContainer()->get(DeactivateWatchFileTasksHandler::class);
        $deactivateHandler(new DeactivateWatchFileTasksAction($watchFile->getId()));

        // Verify all tasks are now CANCELLED (retrieve fresh from DB)
        $collectTaskGateway = self::getContainer()->get(CollectTaskGatewayInterface::class);

        $this->assertNotNull($task1Id, 'Task 1 ID should not be null');
        $this->assertNotNull($task2Id, 'Task 2 ID should not be null');
        $this->assertNotNull($task3Id, 'Task 3 ID should not be null');

        $refreshedTask1 = $collectTaskGateway->get($task1Id);
        $refreshedTask2 = $collectTaskGateway->get($task2Id);
        $refreshedTask3 = $collectTaskGateway->get($task3Id);

        $this->assertEquals(CollectTaskStatus::CANCELLED, $refreshedTask1->getStatus());
        $this->assertEquals(CollectTaskStatus::CANCELLED, $refreshedTask2->getStatus());
        $this->assertEquals(CollectTaskStatus::CANCELLED, $refreshedTask3->getStatus());
    }

    public function testDeactivatingWatchFileHandles404ErrorsGracefully(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create([
                'status' => WatchFileStatus::ENABLED,
                'name' => 'Test WatchFile',
            ])
        ;

        $source = SourceFactory::new()->create([
            'watchFile' => $watchFile,
            'name' => 'Source',
            'status' => SourceStatus::ACTIVE,
        ]);

        // Create CollectTask using Foundry
        $task = CollectTaskFactory::createOne([
            'source' => $source,
            'watchFile' => $watchFile,
            'providerTaskId' => 'bakus-task-404',
            'status' => CollectTaskStatus::QUEUED,
        ]);
        $taskId = $task->getId();

        // Mock 404 response from Bakus (task already deleted)
        $response = $this->createStub(ResponseInterface::class);
        $response
            ->method('getStatusCode')
            ->willReturn(404);

        $exception = new CollectHttpException($response, 'Task not found on provider');

        $providerGateway = $this->createMock(ProviderGatewayInterface::class);
        $providerGateway
            ->expects($this->once())
            ->method('cancelTask')
            ->with('bakus-task-404')
            ->willThrowException($exception);

        self::getContainer()->set(ProviderGatewayInterface::class, $providerGateway);

        // Deactivate the WatchFile
        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/draft");

        $this->assertResponseStatusCodeSame(201);

        // Manually trigger deferred task deactivation
        $deactivateHandler = self::getContainer()->get(DeactivateWatchFileTasksHandler::class);
        $deactivateHandler(new DeactivateWatchFileTasksAction($watchFile->getId()));

        // Verify task is still marked as CANCELLED locally (idempotent cleanup)
        $collectTaskGateway = self::getContainer()->get(CollectTaskGatewayInterface::class);
        $this->assertNotNull($taskId, 'Task ID should not be null');
        $refreshedTask = $collectTaskGateway->get($taskId);
        $this->assertEquals(CollectTaskStatus::CANCELLED, $refreshedTask->getStatus());
    }

    public function testDeactivatingWatchFileWithPartialFailuresContinuesWithOtherTasks(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create([
                'status' => WatchFileStatus::ENABLED,
                'name' => 'Test WatchFile',
            ])
        ;
        $watchFileId = $watchFile->getId();

        // Create 3 active sources
        $source1 = SourceFactory::new()->create([
            'watchFile' => $watchFile,
            'name' => 'Source 1',
            'status' => SourceStatus::ACTIVE,
        ]);

        $source2 = SourceFactory::new()->create([
            'watchFile' => $watchFile,
            'name' => 'Source 2',
            'status' => SourceStatus::ACTIVE,
        ]);

        $source3 = SourceFactory::new()->create([
            'watchFile' => $watchFile,
            'name' => 'Source 3',
            'status' => SourceStatus::ACTIVE,
        ]);

        // Create CollectTasks using Foundry
        $task1 = CollectTaskFactory::createOne([
            'source' => $source1,
            'watchFile' => $watchFile,
            'providerTaskId' => 'bakus-task-1',
            'status' => CollectTaskStatus::QUEUED,
        ]);
        $task1Id = $task1->getId();

        $task2 = CollectTaskFactory::createOne([
            'source' => $source2,
            'watchFile' => $watchFile,
            'providerTaskId' => 'bakus-task-2',
            'status' => CollectTaskStatus::QUEUED,
        ]);
        $task2Id = $task2->getId();

        $task3 = CollectTaskFactory::createOne([
            'source' => $source3,
            'watchFile' => $watchFile,
            'providerTaskId' => 'bakus-task-3',
            'status' => CollectTaskStatus::QUEUED,
        ]);
        $task3Id = $task3->getId();

        // Mock failure on 2nd task (500 error, no retry)
        $response = $this->createStub(ResponseInterface::class);
        $response
            ->method('getStatusCode')
            ->willReturn(500);

        $exception = new CollectHttpException($response, 'Internal Server Error');

        $providerGateway = $this->createMock(ProviderGatewayInterface::class);
        $providerGateway
            ->expects($this->exactly(3)) // 1 attempt per task (no retry)
            ->method('cancelTask')
            ->willReturnCallback(function ($taskId) use ($exception) {
                if ('bakus-task-2' === $taskId) {
                    throw $exception;
                }
            });

        self::getContainer()->set(ProviderGatewayInterface::class, $providerGateway);

        // Deactivate the WatchFile
        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/draft");

        $this->assertResponseStatusCodeSame(201);

        // Manually trigger deferred task deactivation
        $deactivateHandler = self::getContainer()->get(DeactivateWatchFileTasksHandler::class);
        $deactivateHandler(new DeactivateWatchFileTasksAction($watchFile->getId()));

        // Verify tasks 1 and 3 are CANCELLED, task 2 remains QUEUED
        $collectTaskGateway = self::getContainer()->get(CollectTaskGatewayInterface::class);

        $this->assertNotNull($task1Id, 'Task 1 ID should not be null');
        $this->assertNotNull($task2Id, 'Task 2 ID should not be null');
        $this->assertNotNull($task3Id, 'Task 3 ID should not be null');

        $refreshedTask1 = $collectTaskGateway->get($task1Id);
        $refreshedTask2 = $collectTaskGateway->get($task2Id);
        $refreshedTask3 = $collectTaskGateway->get($task3Id);

        $this->assertEquals(CollectTaskStatus::CANCELLED, $refreshedTask1->getStatus());
        $this->assertEquals(CollectTaskStatus::QUEUED, $refreshedTask2->getStatus()); // Failed to cancel
        $this->assertEquals(CollectTaskStatus::CANCELLED, $refreshedTask3->getStatus());

        // Verify WatchFile is still DRAFT despite partial failure
        $watchFileGateway = self::getContainer()->get(WatchFileGatewayInterface::class);
        $refreshedWatchFile = $watchFileGateway->get($watchFileId);
        $this->assertEquals(WatchFileStatus::DRAFT, $refreshedWatchFile->getStatus());
    }
}
