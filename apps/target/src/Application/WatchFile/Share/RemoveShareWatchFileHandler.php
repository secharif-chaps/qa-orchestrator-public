<?php

namespace App\Application\WatchFile\Share;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\User\UserGatewayInterface;
use App\Domain\WatchFile\Event\WatchFileUnsharedEvent;
use App\Domain\WatchFile\Exception\CannotRemoveOwnerException;
use App\Domain\WatchFile\WatchFileUserGatewayInterface;
use App\Domain\WatchFile\WatchFileUserRole;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

#[AsMessageHandler]
readonly class RemoveShareWatchFileHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private WatchFileUserGatewayInterface $watchFileUserGateway,
        private UserGatewayInterface $userGateway,
        private NotifierInterface $notifier,
        private ShareWatchFileNotificationFactory $notificationFactory,
        private EventDispatcherInterface $eventDispatcher,
        private RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(RemoveShareWatchFileAction $action): void
    {
        $watchFile = $this->getWatchFile($action->watchFileId);
        $watchFileUser = $this->watchFileUserGateway->get($action->watchFileUserId);

        if ($watchFile->getId() !== $watchFileUser->getWatchFile()?->getId()) {
            throw new \InvalidArgumentException(\sprintf(
                'The watch file ID "%s" does not match the watch file user ID "%s".',
                $action->watchFileId,
                $action->watchFileUserId
            ));
        }

        if (WatchFileUserRole::OWNER === $watchFileUser->getRole()) {
            throw new CannotRemoveOwnerException($watchFileUser);
        }

        $removedBy = $this->userGateway->get($action->removedByUserId);

        // Dispatch event for activity logging BEFORE removing the entity to preserve the ID
        $this->eventDispatcher->dispatch(new WatchFileUnsharedEvent($watchFile, $watchFileUser, $removedBy));

        // Now remove the entity from database
        $this->watchFileUserGateway->remove($watchFileUser);
        $email = $watchFileUser->getUser()?->getEmail();
        if (!\is_string($email) || empty($email)) {
            $this->logger?->warning(
                'Skipping notification for user without email',
                [
                    'userId' => $watchFileUser->getUser()?->getId(),
                    'watchFileId' => $watchFileUser->getWatchFile()?->getId(),
                ],
            );

            return;
        }

        $this->notifier->send(
            $this->notificationFactory->makeRemovedNotification($watchFileUser, $removedBy, $action->removedAt),
            new Recipient($email),
        );

        $this->realTimeUpdatePublisher->publishWatchFileUpdate($watchFile);
    }
}
