<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Source;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Shared\AccessDeniedException;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceStatusChangedEvent;
use App\Domain\WatchFile\WatchFileActiveException;
use App\Domain\WatchFile\WatchFileStatus;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ChangeSourceStatusHandler
{
    use GetSourceTrait;
    use GetWatchFileTrait;
    use HandleTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ChangeSourceStatusAction $action): Source
    {
        $source = $this->getSource($action->sourceId);

        // Get watchFileId from source if not provided
        $watchFileId = $action->watchFileId ?? $source->getWatchFile()
                    ->getId();

        $watchFile = $this->getWatchFile($watchFileId);

        // Check if the watch file is in active status
        if (WatchFileStatus::ENABLED === $watchFile->getStatus()) {
            throw new WatchFileActiveException($watchFileId, 'change source status');
        }

        // Verify that the source belongs to the specified watch file
        if ($source->getWatchFile()->getId() !== $watchFile->getId()) {
            throw new AccessDeniedException(\sprintf(
                'Source belongs to watch file %s but accessed from watch file %s. Cross-WatchFile access not allowed.',
                $source->getWatchFile()->getId(),
                $watchFile->getId()
            ), );
        }

        // Store old status for logging
        $oldStatus = $source->getStatus();

        // Change the source status
        if (SourceStatus::ACTIVE === $action->status) {
            $source->activate();
        } else {
            $source->deactivate();
        }

        $this->sourceGateway->save($source);

        // Dispatch the event if user is provided
        if (null !== $action->user) {
            $this->eventDispatcher->dispatch(new SourceStatusChangedEvent(
                source: $source,
                watchFile: $watchFile,
                user: $action->user,
                status: $action->status,
                oldStatus: $oldStatus
            ));
        }

        $this->logger?->info('Source status changed successfully', [
            'watch_file_id' => $watchFile->getId(),
            'source_id' => $source->getId(),
            'new_status' => $action->status->value,
        ]);

        return $source;
    }
}
