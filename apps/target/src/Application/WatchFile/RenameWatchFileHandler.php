<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\Event\WatchFileUpdatedEvent;
use App\Domain\WatchFile\WatchFile;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class RenameWatchFileHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(RenameWatchFileAction $action): WatchFile
    {
        $watchFile = $this->getWatchFile($action->watchFileId);
        $oldName = $watchFile->getName();

        $watchFile->setName($action->name);
        $watchFile->setTitleManuallySetByUser($action->isManualRename);

        $this->watchFileGateway->save($watchFile);

        // Publish real-time update to all authorized users
        $this->realTimeUpdatePublisher->publishWatchFileUpdate($watchFile);

        // Dispatch event for activity logging using the watchfile creator
        $user = $watchFile->getCreatedBy();
        if ($user instanceof User) {
            $changes = [
                'name' => [
                    'old' => $oldName,
                    'new' => $action->name,
                ],
            ];
            $this->eventDispatcher->dispatch(new WatchFileUpdatedEvent($watchFile, $user, $changes));
        }

        $this->logger?->info('WatchFile renamed successfully', [
            'watch_file_id' => $watchFile->getId(),
            'new_name' => $action->name,
            'manually_set' => $action->isManualRename,
        ]);

        return $watchFile;
    }
}
