<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Collect\Task\CreateCollectTaskAction;
use App\Application\Collect\Task\CreateCollectTaskHandler;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\ProviderGatewayInterface;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\SourceStatus;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileStatus;

class WatchFileCollectTaskActivationTest extends AbstractApiTestCase
{
    public function testActivatingWatchFileCreatesCollectTasksForAllActiveSources(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create([
                'status' => WatchFileStatus::DRAFT,
                'name' => 'Test WatchFile',
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
        ;

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

        // Create 1 inactive source that should be ignored
        SourceFactory::new()->create([
            'watchFile' => $watchFile,
            'name' => 'Inactive Source',
            'status' => SourceStatus::INACTIVE,
        ]);

        // Mock the ProviderGateway to return task IDs
        $providerGateway = $this->createMock(ProviderGatewayInterface::class);
        $providerGateway
            ->expects($this->exactly(3))
            ->method('createTask')
            ->willReturnOnConsecutiveCalls('bakus-task-id-1', 'bakus-task-id-2', 'bakus-task-id-3');

        $providerGateway
            ->expects($this->exactly(3))
            ->method('getTaskStatus')
            ->willReturn(CollectTaskStatus::QUEUED);

        self::getContainer()->set(ProviderGatewayInterface::class, $providerGateway);

        // Activate the WatchFile
        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        $this->assertResponseStatusCodeSame(201);

        // Manually trigger deferred task creation for each source
        // DispatchAfterCurrentBusStamp() defers messages until after the HTTP transaction,
        // so we must manually call the handler to simulate async processing in tests
        $createCollectTaskHandler = self::getContainer()->get(CreateCollectTaskHandler::class);
        $createCollectTaskHandler(new CreateCollectTaskAction($source1->getId(), $watchFile->getId(), start: true));
        $createCollectTaskHandler(new CreateCollectTaskAction($source2->getId(), $watchFile->getId(), start: true));
        $createCollectTaskHandler(new CreateCollectTaskAction($source3->getId(), $watchFile->getId(), start: true));

        // Verify CollectTasks were created for all active sources
        $collectTaskGateway = self::getContainer()->get(CollectTaskGatewayInterface::class);
        $tasks = $collectTaskGateway->findAllByWatchFileId($watchFile->getId());

        $this->assertCount(3, $tasks, 'Expected 3 CollectTasks to be created for 3 active sources');

        // Verify all tasks are in QUEUED status
        foreach ($tasks as $task) {
            $this->assertEquals(CollectTaskStatus::QUEUED, $task->getStatus());
            $this->assertNotNull($task->getProviderTaskId());
        }
    }

    public function testActivatingWatchFileWithPartialFailuresFlagsFailedTasksCorrectly(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create([
                'status' => WatchFileStatus::DRAFT,
                'name' => 'Test WatchFile',
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
        ;

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

        // Mock the ProviderGateway to fail on the 2nd source
        $providerGateway = $this->createMock(ProviderGatewayInterface::class);
        $providerGateway
            ->expects($this->exactly(3))
            ->method('createTask')
            ->willReturnOnConsecutiveCalls(
                'bakus-task-id-1',
                $this->throwException(new CollectException('Bakus API error for source 2')),
                'bakus-task-id-3'
            );

        // getTaskStatus will only be called for successful tasks (2 times, not 3)
        $providerGateway
            ->expects($this->exactly(2))
            ->method('getTaskStatus')
            ->willReturn(CollectTaskStatus::QUEUED);

        self::getContainer()->set(ProviderGatewayInterface::class, $providerGateway);

        // Activate the WatchFile
        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        $this->assertResponseStatusCodeSame(201);

        // Manually trigger deferred task creation for each source
        // DispatchAfterCurrentBusStamp() defers messages until after the HTTP transaction,
        // so we must manually call the handler to simulate async processing in tests
        $createCollectTaskHandler = self::getContainer()->get(CreateCollectTaskHandler::class);
        $createCollectTaskHandler(new CreateCollectTaskAction($source1->getId(), $watchFile->getId(), start: true));
        $createCollectTaskHandler(new CreateCollectTaskAction($source2->getId(), $watchFile->getId(), start: true));
        $createCollectTaskHandler(new CreateCollectTaskAction($source3->getId(), $watchFile->getId(), start: true));

        // Verify CollectTasks were created for all sources
        $collectTaskGateway = self::getContainer()->get(CollectTaskGatewayInterface::class);
        $tasks = $collectTaskGateway->findAllByWatchFileId($watchFile->getId());

        $this->assertCount(3, $tasks, 'Expected 3 CollectTasks to be created (including the failed one)');

        // Find tasks by source
        $tasksBySource = [];
        foreach ($tasks as $task) {
            $sourceId = $task
                ->getSource()
                ->getId();

            $tasksBySource[$sourceId] = $task;
        }

        // Verify task 1 succeeded
        $task1 = $tasksBySource[$source1->getId()];
        $this->assertEquals(CollectTaskStatus::QUEUED, $task1->getStatus());
        $this->assertEquals('bakus-task-id-1', $task1->getProviderTaskId());

        // Verify task 2 failed
        $task2 = $tasksBySource[$source2->getId()];
        $this->assertEquals(CollectTaskStatus::FAILED, $task2->getStatus());
        $this->assertNull($task2->getProviderTaskId());

        // Verify task 3 succeeded
        $task3 = $tasksBySource[$source3->getId()];
        $this->assertEquals(CollectTaskStatus::QUEUED, $task3->getStatus());
        $this->assertEquals('bakus-task-id-3', $task3->getProviderTaskId());

        // Verify WatchFile remains active
        $watchFileGateway = self::getContainer()->get(WatchFileGatewayInterface::class);
        $refreshedWatchFile = $watchFileGateway->get($watchFile->getId());
        $this->assertEquals(WatchFileStatus::ENABLED, $refreshedWatchFile->getStatus());
    }

    public function testActivatingWatchFileIgnoresInactiveSources(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create([
                'status' => WatchFileStatus::DRAFT,
                'name' => 'Test WatchFile',
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
        ;

        // Create 1 active source
        $activeSource = SourceFactory::new()->create([
            'watchFile' => $watchFile,
            'name' => 'Active Source',
            'status' => SourceStatus::ACTIVE,
        ]);

        // Create 2 inactive sources
        SourceFactory::new()->create([
            'watchFile' => $watchFile,
            'name' => 'Inactive Source 1',
            'status' => SourceStatus::INACTIVE,
        ]);

        SourceFactory::new()->create([
            'watchFile' => $watchFile,
            'name' => 'Inactive Source 2',
            'status' => SourceStatus::INACTIVE,
        ]);

        // Mock the ProviderGateway to expect only 1 call
        $providerGateway = $this->createMock(ProviderGatewayInterface::class);
        $providerGateway
            ->expects($this->once())
            ->method('createTask')
            ->willReturn('bakus-task-id-1');

        $providerGateway
            ->expects($this->once())
            ->method('getTaskStatus')
            ->willReturn(CollectTaskStatus::QUEUED);

        self::getContainer()->set(ProviderGatewayInterface::class, $providerGateway);

        // Activate the WatchFile
        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        $this->assertResponseStatusCodeSame(201);

        // Manually trigger deferred task creation for the active source
        // DispatchAfterCurrentBusStamp() defers messages until after the HTTP transaction,
        // so we must manually call the handler to simulate async processing in tests
        $createCollectTaskHandler = self::getContainer()->get(CreateCollectTaskHandler::class);
        $createCollectTaskHandler(new CreateCollectTaskAction(
            $activeSource->getId(),
            $watchFile->getId(),
            start: true
        ));

        // Verify only 1 CollectTask was created
        $collectTaskGateway = self::getContainer()->get(CollectTaskGatewayInterface::class);
        $tasks = $collectTaskGateway->findAllByWatchFileId($watchFile->getId());

        $this->assertCount(1, $tasks, 'Expected only 1 CollectTask for the single active source');
        $this->assertEquals(CollectTaskStatus::QUEUED, $tasks[0]->getStatus());
    }
}
