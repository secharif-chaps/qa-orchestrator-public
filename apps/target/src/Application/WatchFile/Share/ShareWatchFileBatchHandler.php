<?php

namespace App\Application\WatchFile\Share;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\User\UserGatewayInterface;
use App\Domain\WatchFile\Event\WatchFileSharedEvent;
use App\Domain\WatchFile\Exception\WatchFileUserNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserGatewayInterface;
use App\Domain\WatchFile\WatchFileUserLimitExceededException;
use App\Domain\WatchFile\WatchFileUserRole;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
class ShareWatchFileBatchHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private readonly WatchFileUserGatewayInterface $watchFileUserGateway,
        private readonly UserGatewayInterface $userGateway,
        private readonly NotifierInterface $notifier,
        private readonly ShareWatchFileNotificationFactory $notificationFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return list<WatchFileUser>
     */
    public function __invoke(ShareWatchFileBatchAction $action): array
    {
        $usersUpdated = [];
        $usersAdded = [];
        $watchFileUpdated = [];

        $sharedBy = $this->userGateway->get($action->sharedByUserId);

        $watchFileUserList = [];
        foreach ($action->shareWatchFileActions as $shareWatchFileAction) {
            $watchFile = $this->getWatchFile($shareWatchFileAction->watchFileId);
            $user = $this->userGateway->get($shareWatchFileAction->userId);

            $this->ensureLimitsSharedNotExceeded($watchFile);

            try {
                $watchFileUser = $this->watchFileUserGateway->getByWatchFileAndUser($watchFile, $user);
                if ($watchFileUser->getRole() !== $shareWatchFileAction->role) {
                    // Update the role if it has changed
                    $watchFileUser->setRole($shareWatchFileAction->role);
                    $watchFileUser->updateBy($sharedBy);

                    $this->watchFileUserGateway->save($watchFileUser);
                    $usersUpdated[] = $watchFileUser;
                    $watchFileUpdated[$shareWatchFileAction->watchFileId] = $watchFile;

                    // Dispatch event for activity logging (role update is considered a share event)
                    $this->eventDispatcher->dispatch(new WatchFileSharedEvent($watchFile, $watchFileUser, $sharedBy));
                }
            } catch (WatchFileUserNotFoundException) {
                $watchFileUser = new WatchFileUser($watchFile, $user, $shareWatchFileAction->role);

                $watchFileUser->setCreatedBy($sharedBy);

                $this->watchFileUserGateway->save($watchFileUser);
                $usersAdded[] = $watchFileUser;
                $watchFileUpdated[$shareWatchFileAction->watchFileId] = $watchFile;

                // Dispatch event for activity logging
                $this->eventDispatcher->dispatch(new WatchFileSharedEvent($watchFile, $watchFileUser, $sharedBy));
            }

            $watchFileUserList[] = $watchFileUser;
        }

        // send notification to users
        if (\count($usersAdded) > 0) {
            foreach ($usersAdded as $watchFileUser) {
                if (WatchFileUserRole::OWNER === $watchFileUser->getRole()) {
                    // Skip sending notification for owner role
                    continue;
                }

                $email = $watchFileUser->getUser()?->getEmail();
                if (!\is_string($email) || empty($email)) {
                    $this->logger?->warning(
                        'Skipping notification for user without email',
                        [
                            'userId' => $watchFileUser->getUser()?->getId(),
                            'watchFileId' => $watchFileUser->getWatchFile()?->getId(),
                        ],
                    );

                    continue;
                }

                $this->notifier->send(
                    $this->notificationFactory->makeAddedNotification($watchFileUser, $sharedBy, $action->sharedAt),
                    new Recipient($email),
                );
            }
        }

        if (\count($usersUpdated) > 0) {
            foreach ($usersUpdated as $watchFileUser) {
                $email = $watchFileUser->getUser()?->getEmail();
                if (!\is_string($email) || empty($email)) {
                    $this->logger?->warning(
                        'Skipping notification for user without email',
                        [
                            'userId' => $watchFileUser->getUser()?->getId(),
                            'watchFileId' => $watchFileUser->getWatchFile()?->getId(),
                        ],
                    );

                    continue;
                }

                $this->notifier->send(
                    $this->notificationFactory->makeUpdatedNotification($watchFileUser, $sharedBy, $action->sharedAt),
                    new Recipient($email),
                );
            }
        }

        if (\count($watchFileUpdated) > 0) {
            foreach ($watchFileUpdated as $watchFile) {
                $this->realTimeUpdatePublisher->publishWatchFileUpdate($watchFile);
            }
        }

        return $watchFileUserList;
    }

    private function ensureLimitsSharedNotExceeded(WatchFile $watchFile): void
    {
        try {
            $count = $this->watchFileUserGateway->countByWatchFile($watchFile);

            Assert::lessThanEq(
                $count,
                WatchFile::MAX_WATCHFILE_USERS,
                \sprintf('The watch file "%s" has reached the maximum number of shares (%d).',
                    $watchFile->getId(),
                    WatchFile::MAX_WATCHFILE_USERS,
                ),
            );
        } catch (\InvalidArgumentException $e) {
            throw new WatchFileUserLimitExceededException($e->getMessage(), previous: $e);
        }
    }
}
