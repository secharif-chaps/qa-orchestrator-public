<?php

declare(strict_types=1);

namespace App\Application\Actor;

use App\Domain\Actor\ActorStatus;
use App\Domain\Actor\ActorStatusChangedEvent;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceNotFoundException;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceStatusChangedEvent;
use App\Domain\WatchFile\WatchFileActiveException;
use App\Domain\WatchFile\WatchFileActor;
use App\Domain\WatchFile\WatchFileActorGatewayInterface;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileStatus;
use App\UserInterface\Dto\Actor\ChangeActorStatusOutputDto;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ChangeActorStatusHandler
{
    public function __construct(
        private SourceGatewayInterface $sourceGateway,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private WatchFileActorGatewayInterface $watchFileActorGateway,
        private WatchFileGatewayInterface $watchFileGateway,
    ) {
    }

    public function __invoke(ChangeActorStatusAction $action): ChangeActorStatusOutputDto
    {
        // Get the watchfile to check its status
        $watchFile = $this->watchFileGateway->get($action->watchFileId);

        // Check if the watchfile is in active status
        if (WatchFileStatus::ENABLED === $watchFile->getStatus()) {
            throw new WatchFileActiveException($action->watchFileId, 'change actor status');
        }

        $watchFileActor = $this->watchFileActorGateway->findByActorAndWatchFile($action->actorId, $action->watchFileId);
        $oldStatus = $watchFileActor->getStatus();
        $watchFileActor->setStatus($action->newStatus);
        $this->watchFileActorGateway->save($watchFileActor);

        $sources = [];

        // Only update sources if sourceIds are provided
        if (!empty($action->sourceIds)) {
            $sources = $this->sourceGateway->findByActor($action->actorId);
            $sources = array_filter($sources, function (Source $source) use ($action) {
                return \in_array($source->getId(), $action->sourceIds, true);
            });
            if (\count($sources) < \count($action->sourceIds)) {
                $this->logger->warning('Some given sources do not belong to watchfile or do not exist', [
                    'watchFileId' => $action->watchFileId,
                    'actorId' => $action->actorId,
                    'requested_sources' => $action->sourceIds,
                    'found_sources' => array_map(fn (Source $source) => $source->getId(), $sources),
                ]);
                throw new SourceNotFoundException('Unable to find sources');
            }

            if (ActorStatus::INACTIVE === $action->newStatus) {
                // We do not update manually deactivated sources
                $sources = array_filter($sources, function (Source $source) {
                    return SourceStatus::INACTIVE !== $source->getStatus();
                });
            }

            $this->sourceGateway->updateSourcesStatus($sources, $action->newStatus->toSourceStatus());
        }

        $this->dispatchEvents($action, $watchFileActor, $sources, $oldStatus);

        return new ChangeActorStatusOutputDto($watchFileActor->getActor(), $sources);
    }

    /**
     * @param Source[] $sources
     */
    private function dispatchEvents(
        ChangeActorStatusAction $action,
        WatchFileActor $watchFileActor,
        array $sources,
        ActorStatus $oldStatus,
    ): void {
        $this->eventDispatcher->dispatch(new ActorStatusChangedEvent(
            actor: $watchFileActor->getActor(),
            watchFile: $watchFileActor->getWatchFile(),
            user: $action->user,
            status: $action->newStatus,
            oldStatus: $oldStatus,
        ));
        foreach ($sources as $source) {
            $this->eventDispatcher->dispatch(new SourceStatusChangedEvent(
                source: $source,
                watchFile: $watchFileActor->getWatchFile(),
                user: $action->user,
                status: $action->newStatus->toSourceStatus(),
                oldStatus: $source->getStatus(),
            ));
        }
    }
}
