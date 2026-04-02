<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Quota;

use App\Application\WatchFile\ChangeWatchFileStatusAction;
use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\UsageLimit\UsageLimitConfigInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\Exception\WatchFileOwnerNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;
use App\Domain\WatchFileActivity\WatchFileActivityLoggerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class CheckDocumentQuotaHandler
{
    use GetWatchFileTrait;
    use HandleTrait;

    public function __construct(
        private readonly UsageLimitConfigInterface $usageLimitConfig,
        private readonly WatchFileActivityLoggerInterface $activityLogger,
        private readonly WatchFileActivityGatewayInterface $activityGateway,
        private readonly DocumentGatewayInterface $documentGateway,
        MessageBusInterface $messageBus,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->messageBus = $messageBus;
    }

    public function __invoke(CheckDocumentQuotaAction $action): int
    {
        $quotaLimit = $this->usageLimitConfig->documentMaxPerWatchFile();
        $quota = $action->quota ?? $quotaLimit->value();

        if (null === $quota) {
            $this->logger?->info('Document quota check skipped: no quota limit configured');

            return 0;
        }

        $this->logger?->info('Starting document quota check', [
            'quota' => $quota,
        ]);

        $watchFilesWithCounts = $this->documentGateway->findWatchFilesExceedingDocumentQuota($quota);

        if (empty($watchFilesWithCounts)) {
            $this->logger?->info('No WatchFiles exceed document quota');

            return 0;
        }

        $this->logger?->info('Found WatchFiles exceeding document quota', [
            'count' => \count($watchFilesWithCounts),
        ]);

        $processedCount = 0;
        foreach ($watchFilesWithCounts as $watchFileId => $documentCount) {
            try {
                $watchFile = $this->getWatchFile($watchFileId, null);

                $owner = $this->getWatchFileOwner($watchFile);

                if (WatchFileStatus::ENABLED !== $watchFile->getStatus()) {
                    $this->logger?->debug('WatchFile already in DRAFT status, skipping', [
                        'watch_file_id' => $watchFileId,
                    ]);

                    continue;
                }

                $this->handle(new ChangeWatchFileStatusAction($watchFileId, WatchFileStatus::DRAFT));

                $activity = $this->activityLogger->logDocumentQuotaExceeded(
                    $watchFile,
                    $owner,
                    $documentCount,
                    $quota,
                );
                $this->activityGateway->save($activity);

                $this->logger?->info('WatchFile moved to DRAFT due to document quota exceeded', [
                    'watch_file_id' => $watchFileId,
                    'document_count' => $documentCount,
                    'quota' => $quota,
                ]);

                ++$processedCount;
            } catch (\Exception $e) {
                $this->logger?->error('Failed to process WatchFile for document quota check', [
                    'watch_file_id' => $watchFileId,
                    'error' => $e->getMessage(),
                    'exception_type' => $e::class,
                ]);
            }
        }

        $this->logger?->info('Document quota check completed', [
            'processed_count' => $processedCount,
            'total_exceeding' => \count($watchFilesWithCounts),
        ]);

        return $processedCount;
    }

    private function getWatchFileOwner(WatchFile $watchFile): User
    {
        /** @var WatchFileUser $watchFileUser */
        foreach ($watchFile->getWatchFileUsers() as $watchFileUser) {
            if (WatchFileUserRole::OWNER === $watchFileUser->getRole()) {
                $owner = $watchFileUser->getUser();
                if (null !== $owner) {
                    return $owner;
                }
            }
        }

        $createdBy = $watchFile->getCreatedBy();
        if (null !== $createdBy) {
            return $createdBy;
        }

        throw new WatchFileOwnerNotFoundException($watchFile->getId());
    }
}
