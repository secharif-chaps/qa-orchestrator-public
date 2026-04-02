<?php

declare(strict_types=1);

namespace App\Application\Collect\Task;

use App\Application\WatchFile\GetWatchFileTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler]
readonly class ActivateWatchFileTasksHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ActivateWatchFileTasksAction $action): void
    {
        $watchFile = $this->getWatchFile($action->watchFileId);
        if (!$watchFile->isActive()) {
            $this->logger?->warning(
                'Attempted to activate collect tasks for an inactive watch file',
                [
                    'watch_file_id' => $watchFile->getId(),
                ],
            );

            return;
        }

        $activeSources = [];
        $inactiveSources = [];

        foreach ($watchFile->getSources() as $source) {
            if (!$source->isActive()) {
                $inactiveSources[] = [
                    'id' => $source->getId(),
                    'name' => $source->getName(),
                    'type' => $source
                        ->getType()
                        ->value,
                ];

                continue;
            }

            $activeSources[] = [
                'id' => $source->getId(),
                'name' => $source->getName(),
                'type' => $source
                    ->getType()
                    ->value,
            ];

            $this->messageBus->dispatch(
                new CreateCollectTaskAction($source->getId(), $watchFile->getId()),
                [new DispatchAfterCurrentBusStamp()],
            );
        }

        $this->logger?->info('CollectTask activation dispatched for WatchFile', [
            'watch_file_id' => $watchFile->getId(),
            'watch_file_name' => $watchFile->getName(),
            'active_sources_count' => \count($activeSources),
            'inactive_sources_count' => \count($inactiveSources),
            'active_sources' => $activeSources,
            'inactive_sources' => $inactiveSources,
        ]);
    }
}
