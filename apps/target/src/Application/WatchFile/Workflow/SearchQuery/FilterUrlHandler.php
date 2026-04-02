<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Workflow\SearchQuery;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\WatchFile\WatchFileState;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\HandleTrait;

#[AsMessageHandler]
class FilterUrlHandler
{
    use GetWatchFileTrait;
    use HandleTrait;

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(FilterUrlAction $action): void
    {
        $watchFile = $this->getWatchFile($action->watchFileId);
        if ($watchFile->getState()->canTransitionTo(WatchFileState::FILTER_URLS)) {
            $this->logger?->info('Filter URL generation already started', [
                'watch_file_id' => $watchFile->getId(),
            ]);

            return;
        }
    }
}
