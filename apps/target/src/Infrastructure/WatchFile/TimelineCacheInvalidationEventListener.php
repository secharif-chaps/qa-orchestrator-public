<?php

namespace App\Infrastructure\WatchFile;

use App\Domain\Actor\ActorStatusChangedEvent;
use App\Domain\Source\Event\SourceAddedToWatchFileEvent;
use App\Domain\Source\SourceStatusChangedEvent;
use App\Domain\WatchFile\Event\ActorAddedEvent;
use App\Domain\WatchFile\Event\WatchFileCreatedEvent;
use App\Domain\WatchFile\Event\WatchFileRelatedEventInterface;
use App\Domain\WatchFile\Event\WatchFileStatusChangedEvent;
use App\Domain\WatchFile\Event\WatchFileUpdatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(WatchFileCreatedEvent::class)]
#[AsEventListener(WatchFileUpdatedEvent::class)]
#[AsEventListener(WatchFileStatusChangedEvent::class)]
#[AsEventListener(ActorAddedEvent::class)]
#[AsEventListener(ActorStatusChangedEvent::class)]
#[AsEventListener(SourceStatusChangedEvent::class)]
#[AsEventListener(SourceAddedToWatchFileEvent::class)]
readonly class TimelineCacheInvalidationEventListener
{
    public function __construct(
        private TimelineCacheService $timelineCacheService,
    ) {
    }

    public function __invoke(WatchFileRelatedEventInterface $event): void
    {
        $this->timelineCacheService->invalidateTimeline($event->getWatchFile());
    }
}
