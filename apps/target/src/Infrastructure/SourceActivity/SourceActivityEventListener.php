<?php

declare(strict_types=1);

namespace App\Infrastructure\SourceActivity;

use App\Domain\Source\Event\SourceAddedToWatchFileEvent;
use App\Domain\Source\SourceStatusChangedEvent;
use App\Domain\SourceActivity\SourceActivityGatewayInterface;
use App\Domain\SourceActivity\SourceActivityLoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class SourceActivityEventListener
{
    public function __construct(
        private readonly SourceActivityLoggerInterface $sourceActivityLogger,
        private readonly SourceActivityGatewayInterface $sourceActivityGateway,
    ) {
    }

    #[AsEventListener]
    public function onSourceStatusChanged(SourceStatusChangedEvent $event): void
    {
        $sourceActivity = $this->sourceActivityLogger->logSourceStatusChanged(
            $event->source,
            $event->user,
            $event->oldStatus,
            $event->status,
            [
                'watch_file_name' => $event->watchFile->getName(),
                'automatic_trigger' => true,
            ]
        );
        $this->sourceActivityGateway->save($sourceActivity);
    }

    #[AsEventListener]
    public function onSourceAddedToWatchFile(SourceAddedToWatchFileEvent $event): void
    {
        $sourceActivity = $this->sourceActivityLogger->logSourceAddedToWatchFile(
            $event->source,
            $event->user,
            [
                'watch_file_name' => $event->watchFile->getName(),
                'automatic_trigger' => true,
            ]
        );
        $this->sourceActivityGateway->save($sourceActivity);
    }
}
