<?php

declare(strict_types=1);

namespace App\Application\Collect\Task;

use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Event\CollectTaskCreatedEvent;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\Exception\CollectTaskAlreadyExistsException;
use App\Domain\Collect\Exception\InactiveSourceException;
use App\Domain\Collect\Exception\InactiveWatchFileException;
use App\Domain\Collect\Exception\NotSupportedCollectorException;
use App\Domain\Collect\ProviderGatewayLocatorInterface;
use App\Domain\Collect\ProviderResolverInterface;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler]
readonly class CreateCollectTaskHandler
{
    public function __construct(
        private CollectTaskGatewayInterface $collectTaskGateway,
        private SourceGatewayInterface $sourceGateway,
        private WatchFileGatewayInterface $watchFileGateway,
        private ProviderResolverInterface $providerResolver,
        private ProviderGatewayLocatorInterface $providerLocator,
        private EventDispatcherInterface $eventDispatcher,
        private MessageBusInterface $messageBus,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(CreateCollectTaskAction $action): CollectTask
    {
        $watchFile = $this->watchFileGateway->get($action->watchFileId);
        if (!$watchFile->isActive()) {
            throw InactiveWatchFileException::forWatchFileId($action->watchFileId);
        }

        $source = $this->sourceGateway->get($action->sourceId);
        if (!$source->isActive()) {
            throw InactiveSourceException::forSourceId($action->sourceId);
        }

        $collectTaskAlreadyExists = $this->collectTaskGateway->findActiveBySourceId($action->sourceId);
        if (\count($collectTaskAlreadyExists) > 0) {
            throw CollectTaskAlreadyExistsException::forSourceId($action->sourceId);
        }

        // created CollectTask
        $providerName = $this->providerResolver->resolve($source);
        $collectTask = new CollectTask(source: $source, watchFile: $watchFile, providerName: $providerName);

        $this->collectTaskGateway->save($collectTask);

        $this->eventDispatcher->dispatch(new CollectTaskCreatedEvent($collectTask));

        // start CollectTask
        if ($action->start) {
            $collectTask->getStatus()
                ->throwIfInvalidTransition(CollectTaskStatus::QUEUED);

            $providerGateway = $this->providerLocator->get($providerName);

            try {
                $providerTaskId = $providerGateway->createTask($collectTask);
                $collectTask->start($providerTaskId, $this->eventDispatcher);

                try {
                    $taskStatus = $providerGateway->getTaskStatus($providerTaskId);
                    if ($taskStatus !== $collectTask->getStatus() && null !== $collectTask->getId()) {
                        $updateTaskStatusAction = new UpdateTaskStatusAction(
                            collectTaskId: $collectTask->getId(),
                            status: $taskStatus
                        );
                        $this->messageBus->dispatch($updateTaskStatusAction, [new DispatchAfterCurrentBusStamp()]);
                    }
                } catch (\Throwable $e) {
                    $this->logger?->error('Failed to get task status from provider', [
                        'collect_task_id' => $collectTask->getId(),
                        'source_id' => $source->getId(),
                        'source_name' => $source->getName(),
                        'watch_file_id' => $watchFile->getId(),
                        'provider_task_id' => $providerTaskId,
                        'provider_name' => $providerName,
                    ]);
                }

                $this->logger?->info('CollectTask started successfully', [
                    'collect_task_id' => $collectTask->getId(),
                    'source_id' => $source->getId(),
                    'watch_file_id' => $watchFile->getId(),
                    'provider_task_id' => $providerTaskId,
                    'provider_name' => $providerName,
                ]);
            } catch (CollectException|NotSupportedCollectorException $e) {
                $this->logger?->error('Failed to start CollectTask on provider', [
                    'collect_task_id' => $collectTask->getId(),
                    'source_id' => $source->getId(),
                    'source_name' => $source->getName(),
                    'watch_file_id' => $watchFile->getId(),
                    'watch_file_name' => $watchFile->getName(),
                    'provider_name' => $providerName,
                    'error' => $e->getMessage(),
                    'exception_class' => $e::class,
                ]);

                $collectTask->fail($this->eventDispatcher);
            } catch (\Throwable $e) {
                $this->logger?->critical('Unexpected error while starting CollectTask', [
                    'collect_task_id' => $collectTask->getId(),
                    'source_id' => $source->getId(),
                    'source_name' => $source->getName(),
                    'watch_file_id' => $watchFile->getId(),
                    'watch_file_name' => $watchFile->getName(),
                    'provider_name' => $providerName,
                    'error' => $e->getMessage(),
                    'exception_class' => $e::class,
                    'trace' => $e->getTraceAsString(),
                ]);

                $collectTask->fail($this->eventDispatcher);
            }

            $this->collectTaskGateway->save($collectTask);
        }

        $source->updateCollectStatus($collectTask);

        $this->sourceGateway->save($source);

        return $collectTask;
    }
}
