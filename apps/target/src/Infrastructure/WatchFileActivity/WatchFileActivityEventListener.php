<?php

namespace App\Infrastructure\WatchFileActivity;

use App\Domain\Actor\ActorStatusChangedEvent;
use App\Domain\Source\Event\SourceAddedToWatchFileEvent;
use App\Domain\Source\SourceStatusChangedEvent;
use App\Domain\WatchFile\Event\ActorAddedEvent;
use App\Domain\WatchFile\Event\WatchFileCreatedEvent;
use App\Domain\WatchFile\Event\WatchFileReferenceSubjectUpdatedEvent;
use App\Domain\WatchFile\Event\WatchFileSharedEvent;
use App\Domain\WatchFile\Event\WatchFileStatusChangedEvent;
use App\Domain\WatchFile\Event\WatchFileUnsharedEvent;
use App\Domain\WatchFile\Event\WatchFileUpdatedEvent;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;
use App\Domain\WatchFileActivity\WatchFileActivityLoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Listens to WatchFile domain events and logs activities.
 *
 * IMPORTANT: To avoid duplicate doctrine flush and Mercure publications,
 * this listener only calls touchWatchFile() for events where the corresponding
 * handler does NOT save the WatchFile before dispatching.
 *
 * Events that DO NOT call touchWatchFile() (handler saves WatchFile first):
 * - WatchFileUpdatedEvent (via RenameWatchFileHandler)
 * - WatchFileReferenceSubjectUpdatedEvent (via UpdateWatchFileReferenceSubjectHandler)
 * - WatchFileStatusChangedEvent (via ChangeWatchFileStatusHandler)
 * - ActorAddedEvent (via AddActorHandler)
 * - SourceAddedToWatchFileEvent (via AddSourceHandler)
 *
 * Events that DO call touchWatchFile() (handler does NOT save WatchFile):
 * - SourceStatusChangedEvent (handler saves Source only)
 * - ActorStatusChangedEvent (handler saves WatchFileActor only)
 * - WatchFileSharedEvent (handler doesn't save WatchFile)
 * - WatchFileUnsharedEvent (handler doesn't save WatchFile)
 */
readonly class WatchFileActivityEventListener
{
    public function __construct(
        private WatchFileActivityLoggerInterface $activityLogger,
        private WatchFileActivityGatewayInterface $watchFileActivityGateway,
    ) {
    }

    private function touchWatchFile(WatchFile $watchFile): void
    {
        $watchFile->setUpdatedAt(new \DateTimeImmutable());
    }

    #[AsEventListener]
    public function onWatchFileCreated(WatchFileCreatedEvent $event): void
    {
        $activity = $this->activityLogger->logCreate($event->watchFile, $event->user);
        $this->watchFileActivityGateway->save($activity);
    }

    #[AsEventListener]
    public function onWatchFileUpdated(WatchFileUpdatedEvent $event): void
    {
        if (!empty($event->changes)) {
            $activity = $this->activityLogger->logUpdate(
                $event->watchFile,
                $event->user,
                $event->changes,
                $event->context,
            );
            // Note: WatchFile is already saved by RenameWatchFileHandler (or similar) before this event is dispatched,
            // so we don't need to call touchWatchFile() here. This avoids duplicate Mercure updates.
            $this->watchFileActivityGateway->save($activity);
        }
    }

    #[AsEventListener]
    public function onWatchFileReferenceSubjectUpdated(WatchFileReferenceSubjectUpdatedEvent $event): void
    {
        $activity = $this->activityLogger->logReferenceSubjectUpdate(
            $event->watchFile,
            $event->user,
            $event->oldReferenceSubject,
            $event->newReferenceSubject,
            $event->context,
        );
        // Note: WatchFile is already saved by UpdateWatchFileReferenceSubjectHandler before this event is dispatched,
        // so we don't need to call touchWatchFile() here. This avoids duplicate Mercure updates.
        $this->watchFileActivityGateway->save($activity);
    }

    #[AsEventListener]
    public function onSourceStatusChanged(SourceStatusChangedEvent $event): void
    {
        $activity = $this->activityLogger->logSourceStatusChange(
            $event->source,
            $event->watchFile,
            $event->user,
            $event->status,
            $event->oldStatus,
        );
        $this->touchWatchFile($event->watchFile);
        $this->watchFileActivityGateway->save($activity);
    }

    #[AsEventListener]
    public function onWatchFileStatusChanged(WatchFileStatusChangedEvent $event): void
    {
        $activity = $this->activityLogger->logStatusChange(
            $event->watchFile,
            $event->user,
            $event->oldStatus,
            $event->status,
        );
        // Note: WatchFile is already saved by ChangeWatchFileStatusHandler before this event is dispatched,
        // so we don't need to call touchWatchFile() here. This avoids duplicate Mercure updates.
        $this->watchFileActivityGateway->save($activity);
    }

    #[AsEventListener]
    public function onActorStatusChanged(ActorStatusChangedEvent $event): void
    {
        $activity = $this->activityLogger->logActorStatusChange(
            $event->actor,
            $event->watchFile,
            $event->user,
            $event->status,
            $event->oldStatus
        );
        $this->touchWatchFile($event->watchFile);
        $this->watchFileActivityGateway->save($activity);
    }

    #[AsEventListener]
    public function onWatchFileShared(WatchFileSharedEvent $event): void
    {
        $activity = $this->activityLogger->logShare($event->watchFile, $event->watchFileUser, $event->sharedBy);
        $this->touchWatchFile($event->watchFile);
        $this->watchFileActivityGateway->save($activity);
    }

    #[AsEventListener]
    public function onWatchFileUnshared(WatchFileUnsharedEvent $event): void
    {
        $activity = $this->activityLogger->logUnshare($event->watchFile, $event->watchFileUser, $event->removedBy);
        $this->touchWatchFile($event->watchFile);
        $this->watchFileActivityGateway->save($activity);
    }

    #[AsEventListener]
    public function onActorAdded(ActorAddedEvent $event): void
    {
        $activity = $this->activityLogger->logActorAdd(
            $event->watchFile,
            $event->actor,
            $event->addedBy,
            $event->actorType,
            $event->explanation,
            $event->score
        );
        // Note: WatchFile is already saved by AddActorHandler before this event is dispatched,
        // so we don't need to call touchWatchFile() here. This avoids duplicate Mercure updates.
        $this->watchFileActivityGateway->save($activity);
    }

    #[AsEventListener]
    public function onSourceAddedToWatchFile(SourceAddedToWatchFileEvent $event): void
    {
        $activity = $this->activityLogger->logSourceAdd($event->watchFile, $event->source, $event->user);
        // Note: WatchFile is already saved by AddSourceHandler before this event is dispatched,
        // so we don't need to call touchWatchFile() here. This avoids duplicate Mercure updates.
        $this->watchFileActivityGateway->save($activity);
    }
}
