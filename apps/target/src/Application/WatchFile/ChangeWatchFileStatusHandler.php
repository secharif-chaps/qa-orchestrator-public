<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use App\Application\Collect\Task\ActivateWatchFileTasksAction;
use App\Application\Collect\Task\DeactivateWatchFileTasksAction;
use App\Application\WatchFile\Source\GetSourceTrait;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Chat\MessageNotFoundException;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\UsageLimit\Exception\QuotaExceededException;
use App\Domain\UsageLimit\ResourceCount;
use App\Domain\UsageLimit\UsageLimitConfigInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\Event\WatchFileStatusChangedEvent;
use App\Domain\WatchFile\Exception\WatchFileActivationException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUserRole;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
class ChangeWatchFileStatusHandler
{
    use GetSourceTrait;
    use GetWatchFileTrait;
    use HandleTrait;

    public function __construct(
        private readonly Security $security,
        private readonly EventDispatcherInterface $eventDispatcher,
        private MessageBusInterface $messageBus,
        private readonly UsageLimitConfigInterface $usageLimitConfig,
        private readonly DocumentGatewayInterface $documentGateway,
        private readonly RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
        private readonly MessageGatewayInterface $messageGateway,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ChangeWatchFileStatusAction $action): WatchFile
    {
        $user = $this->resolveUser($action);

        $watchFile = $this->getWatchFile($action->watchFileId, $user);

        $oldStatus = $watchFile->getStatus();

        if ($oldStatus === $action->status) {
            $this->logger?->info('WatchFile status unchanged', [
                'watchFileId' => $action->watchFileId,
                'status' => $action->status->value,
            ]);

            return $watchFile; // No change needed
        }

        $userId = $user->getId();
        if (null === $userId) {
            throw new \RuntimeException('User ID cannot be null');
        }

        // Check quota before unarchiving (owner quota)
        if (WatchFileStatus::ARCHIVED === $oldStatus && WatchFileStatus::ENABLED === $action->status) {
            $ownerId = null;
            foreach ($watchFile->getWatchFileUsers() as $watchFileUser) {
                if (WatchFileUserRole::OWNER === $watchFileUser->getRole()) {
                    $ownerId = $watchFileUser->getUser()?->getId();
                    break;
                }
            }

            if (null !== $ownerId) {
                $this->handle(new CheckWatchFileOwnerQuotaAction(Uuid::fromString($ownerId)));
            }
        }

        // Enabling a WatchFile; check quotas
        if (WatchFileStatus::ENABLED === $action->status) {
            $this->logger?->debug('Checking quotas for enabling WatchFile', [
                'watchFileId' => $action->watchFileId,
                'userId' => $userId,
            ]);

            $activeWatchFiles = $this->watchFileGateway->countActiveByUserId($userId);
            $watchFileQuota = $this->usageLimitConfig->watchFileMaxActivePerUser();
            if ($watchFileQuota->requiresLimitEnforcement($activeWatchFiles)) {
                $quotaValue = $watchFileQuota->value();
                if (null === $quotaValue) {
                    throw new \LogicException('Quota value cannot be null when limit enforcement is required');
                }
                throw QuotaExceededException::forWatchFileActive($activeWatchFiles->value(), $quotaValue);
            }

            $activeSources = $this->sourceGateway->countActiveByWatchFile($watchFile);
            $sourceQuota = $this->usageLimitConfig->sourceMaxActivePerWatchFile();
            if ($sourceQuota->requiresLimitEnforcement($activeSources)) {
                $quotaValue = $sourceQuota->value();
                if (null === $quotaValue) {
                    throw new \LogicException('Quota value cannot be null when limit enforcement is required');
                }
                throw QuotaExceededException::forActiveSourcesInWatchFile($activeSources->value(), $quotaValue);
            }

            // Require at least one active source to enable the WatchFile
            if (0 === $activeSources->value()) {
                throw WatchFileActivationException::missingActiveSource($action->watchFileId);
            }

            // Require a reference subject to enable the WatchFile
            $referenceSubject = $watchFile->getReferenceSubject();
            if (null === $referenceSubject || '' === trim((string) $referenceSubject->en)) {
                throw WatchFileActivationException::missingReferenceSubject($action->watchFileId);
            }
        }

        // Check document quota when enabling WatchFile
        if (WatchFileStatus::ENABLED === $action->status) {
            $documentQuota = $this->usageLimitConfig->documentMaxPerWatchFile();
            $quotaValue = $documentQuota->value();

            if (null !== $quotaValue) {
                $documentCount = $this->documentGateway->countDocumentsForWatchFile($watchFile->getId());

                if ($documentQuota->requiresLimitEnforcement(ResourceCount::fromInt($documentCount))) {
                    throw QuotaExceededException::forDocumentsInWatchFile($documentCount, $quotaValue);
                }
            }
        }

        $watchFile->setStatus($action->status);

        $this->watchFileGateway->save($watchFile);

        // Publish real-time update to all authorized users
        $this->realTimeUpdatePublisher->publishWatchFileUpdate($watchFile);

        $this->logger?->info('WatchFile status changed', [
            'watchFileId' => $action->watchFileId,
            'newStatus' => $action->status->value,
        ]);

        $this->eventDispatcher->dispatch(
            new WatchFileStatusChangedEvent($watchFile, $user, $oldStatus, $action->status),
        );

        // Handle CollectTask lifecycle based on status changes
        $this->handleCollectTaskLifecycle($watchFile->getId(), $oldStatus, $action->status);

        return $watchFile;
    }

    private function resolveUser(ChangeWatchFileStatusAction $action): User
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            return $user;
        }

        $this->logger?->debug('User not authenticated via security, resolving from message', [
            'action' => 'ChangeWatchFileStatus',
            'messageId' => $action->messageId,
        ]);

        if (null === $action->messageId) {
            throw new \RuntimeException('User not authenticated and messageId is null, unable to resolve user');
        }

        try {
            $message = $this->messageGateway->get($action->messageId);
        } catch (MessageNotFoundException) {
            throw new \RuntimeException(\sprintf('Unable to resolve user: message "%s" not found', $action->messageId));
        }

        $user = $message->getCreatedBy();
        if (!$user instanceof User) {
            throw new \RuntimeException(\sprintf(
                'Unable to resolve user: message "%s" has no author',
                $action->messageId
            ));
        }

        return $user;
    }

    private function handleCollectTaskLifecycle(
        string $watchFileId,
        WatchFileStatus $oldStatus,
        WatchFileStatus $newStatus,
    ): void {
        if (WatchFileStatus::ENABLED === $newStatus) {
            $this->logger?->info('Activating CollectTasks for WatchFile', [
                'watchFileId' => $watchFileId,
                'status_change' => $oldStatus->value . ' → ' . $newStatus->value,
            ]);

            $this->messageBus->dispatch(new ActivateWatchFileTasksAction($watchFileId));
        }

        if (WatchFileStatus::ENABLED !== $newStatus) {
            $this->logger?->info('Deactivating CollectTasks for WatchFile', [
                'watchFileId' => $watchFileId,
                'status_change' => $oldStatus->value . ' → ' . $newStatus->value,
            ]);

            $this->messageBus->dispatch(new DeactivateWatchFileTasksAction($watchFileId));
        }
    }
}
