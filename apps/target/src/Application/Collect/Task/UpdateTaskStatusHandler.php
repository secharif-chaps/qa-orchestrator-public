<?php

declare(strict_types=1);

namespace App\Application\Collect\Task;

use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\SourceActivity\SourceActivityGatewayInterface;
use App\Domain\SourceActivity\SourceActivityLoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class UpdateTaskStatusHandler
{
    public function __construct(
        private CollectTaskGatewayInterface $collectTaskGateway,
        private EventDispatcherInterface $eventDispatcher,
        private SourceGatewayInterface $sourceGateway,
        private SourceActivityLoggerInterface $sourceActivityLogger,
        private SourceActivityGatewayInterface $sourceActivityGateway,
    ) {
    }

    public function __invoke(UpdateTaskStatusAction $action): void
    {
        $collectTask = $this->collectTaskGateway->get($action->collectTaskId);
        $oldCollectTaskStatus = $collectTask->getStatus();

        if (CollectTaskStatus::COMPLETED === $action->status) {
            $collectTask->complete($this->eventDispatcher);
        } elseif (CollectTaskStatus::FAILED === $action->status) {
            $collectTask->fail($this->eventDispatcher);
        } elseif (CollectTaskStatus::CANCELLED === $action->status) {
            $collectTask->cancel();
        } elseif (CollectTaskStatus::RUNNING === $action->status) {
            $collectTask->resume($this->eventDispatcher);
        } else {
            $collectTask->updateStatus($action->status);
        }

        $source = $collectTask->getSource();
        $source->updateCollectStatus($collectTask);

        $this->collectTaskGateway->save($collectTask);
        $this->sourceGateway->save($source);

        if ($oldCollectTaskStatus !== $collectTask->getStatus()) {
            $sourceActivity = $this->sourceActivityLogger->logSourceCollectStatusChanged(
                $source,
                null,
                $oldCollectTaskStatus,
                $collectTask->getStatus(),
                [
                    'provider_name' => $collectTask->getProviderName(),
                    'collect_task_id' => $collectTask->getId(),
                ],
            );
            $this->sourceActivityGateway->save($sourceActivity);
        }
    }
}
