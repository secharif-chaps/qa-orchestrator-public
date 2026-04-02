<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Domain\WatchFileEvent\WatchFileEventGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GetRecentEventsContextHandler
{
    public function __construct(
        private readonly WatchFileEventGatewayInterface $eventsContextGateway,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return array<int, WatchFileEvent>
     */
    public function __invoke(GetRecentEventsContextAction $action): array
    {
        $events = $this->eventsContextGateway->getRecentEvents($action->watchFileId, $action->daysBack);

        $this->logger?->info('GetRecentEventsContextHandler: Retrieved events', [
            'watchFileId' => $action->watchFileId,
            'daysBack' => $action->daysBack,
            'eventCount' => \count($events),
        ]);

        return $events;
    }
}
