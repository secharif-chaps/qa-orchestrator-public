<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Source;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\WatchFile\WatchFile;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class AddMultipleSourcesHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(AddMultipleSourcesAction $action): WatchFile
    {
        $this->logger?->info('Add multiple sources action received', [
            'watch_file_id' => $action->watchFileId,
            'source_count' => $action->getSourceCount(),
        ]);

        $watchFile = $this->getWatchFile($action->watchFileId);

        if (!$action->hasSources()) {
            $this->logger?->info('No sources to add', [
                'watch_file_id' => $action->watchFileId,
            ]);

            return $watchFile;
        }

        // Convert to individual actions and dispatch them
        $individualActions = $action->toIndividualActions();

        foreach ($individualActions as $individualAction) {
            $this->messageBus->dispatch($individualAction);
        }

        $this->logger?->info('Multiple sources dispatched successfully', [
            'watch_file_id' => $action->watchFileId,
            'dispatched_count' => \count($individualActions),
        ]);

        return $watchFile;
    }
}
