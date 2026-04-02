<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFileActivity;

use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorStatus;
use App\Domain\Actor\ActorType;
use App\Domain\Organisation\TenantContext;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\User\User;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\UserAccessState;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Domain\WatchFileActivity\WatchFileActivityLoggerInterface;

class WatchFileActivityLogger implements WatchFileActivityLoggerInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function logCreate(WatchFile $watchFile, User $user): WatchFileActivity
    {
        return $this->create(
            $watchFile,
            $user,
            WatchFileActivityActionType::CREATED,
            [
                'watch_file_name' => $watchFile->getName(),
            ],
        );
    }

    /**
     * @param array{old: array<string, mixed>, new: array<string, mixed>} $changes
     * @param array<string, mixed>|null                                   $context
     */
    public function logUpdate(
        WatchFile $watchFile,
        User $user,
        array $changes,
        ?array $context = null,
    ): WatchFileActivity {
        return $this->create(
            $watchFile,
            $user,
            WatchFileActivityActionType::UPDATED,
            [
                'changes' => $changes,
                'context' => $context,
            ],
        );
    }

    public function logSourceStatusChange(
        Source $source,
        WatchFile $watchFile,
        User $user,
        SourceStatus $status,
        SourceStatus $oldStatus,
    ): WatchFileActivity {
        return $this->create(
            $watchFile,
            $user,
            WatchFileActivityActionType::SOURCE_STATUS_CHANGED,
            [
                'source_name' => $source->getName(),
                'source_id' => $source->getId(),
                'source_type' => $source->getType()
->value,
                'source_url' => $source->getUrl(),
                'status' => $status->value,
                'old_status' => $oldStatus->value,
            ],
        );
    }

    public function logStatusChange(
        WatchFile $watchFile,
        User $user,
        WatchFileStatus $oldStatus,
        WatchFileStatus $status,
    ): WatchFileActivity {
        return $this->create(
            $watchFile,
            $user,
            WatchFileActivityActionType::STATUS_CHANGED,
            [
                'old_status' => $oldStatus->value,
                'new_status' => $status->value,
            ],
        );
    }

    public function logActorStatusChange(
        Actor $actor,
        WatchFile $watchFile,
        User $user,
        ActorStatus $status,
        ActorStatus $oldStatus,
    ): WatchFileActivity {
        return $this->create(
            $watchFile,
            $user,
            WatchFileActivityActionType::ACTOR_STATUS_CHANGED,
            [
                'actor_name' => $actor->getLabel(),
                'actor_id' => $actor->getId(),
                'actor_primary_domain' => $actor->getPrimaryDomain(),
                'status' => $status->value,
                'old_status' => $oldStatus->value,
            ],
        );
    }

    /**
     * @param array<string, mixed> $classificationData
     */
    public function logMonitoringTypeDetection(
        WatchFile $watchFile,
        User $user,
        MonitoringType $detectedType,
        array $classificationData,
    ): WatchFileActivity {
        return $this->create(
            $watchFile,
            $user,
            WatchFileActivityActionType::MONITORING_TYPE_DETECTED,
            [
                'detected_monitoring_type' => $detectedType->value,
                'french_type_name' => $classificationData['primaryType'] ?? '',
                'confidence_score' => $classificationData['confidence'] ?? 0,
                'justification' => $classificationData['justification'] ?? '',
                'detected_keywords' => $classificationData['keywords'] ?? [],
                'detected_entities' => $classificationData['entities'] ?? [],
                'secondary_types' => $classificationData['secondaryTypes'] ?? [],
            ],
        );
    }

    public function logReferenceSubjectUpdate(
        WatchFile $watchFile,
        User $user,
        ?TranslatedText $oldReferenceSubject,
        TranslatedText $newReferenceSubject,
        ?array $context = null,
    ): WatchFileActivity {
        return $this->create(
            $watchFile,
            $user,
            WatchFileActivityActionType::REFERENCE_SUBJECT_UPDATED,
            [
                'old_reference_subject' => $oldReferenceSubject?->toArray(),
                'new_reference_subject' => $newReferenceSubject->toArray(),
                'context' => $context,
            ],
        );
    }

    public function logShare(
        WatchFile $watchFile,
        WatchFileUser $watchFileUser,
        User $sharedBy,
    ): WatchFileActivity {
        return $this->create(
            $watchFile,
            $sharedBy,
            WatchFileActivityActionType::SHARED_MODE_CHANGED,
            [
                'user_id' => $watchFileUser->getUser()?->getId(),
                'user_email' => $watchFileUser->getUser()?->getEmail(),
                'watch_file_user_id' => $watchFileUser->getId(),
                'old_value' => UserAccessState::NO_ACCESS->value,
                'new_value' => $watchFileUser->getRole()?->value,
            ],
        );
    }

    public function logUnshare(
        WatchFile $watchFile,
        WatchFileUser $watchFileUser,
        User $removedBy,
    ): WatchFileActivity {
        return $this->create(
            $watchFile,
            $removedBy,
            WatchFileActivityActionType::SHARED_MODE_CHANGED,
            [
                'user_id' => $watchFileUser->getUser()?->getId(),
                'user_email' => $watchFileUser->getUser()?->getEmail(),
                'watch_file_user_id' => $watchFileUser->getId(),
                'old_value' => $watchFileUser->getRole()?->value,
                'new_value' => UserAccessState::NO_ACCESS->value,
            ],
        );
    }

    public function logActorAdd(
        WatchFile $watchFile,
        Actor $actor,
        User $addedBy,
        ActorType $actorType,
        ?TranslatedText $explanation = null,
        ?float $score = null,
    ): WatchFileActivity {
        return $this->create(
            $watchFile,
            $addedBy,
            WatchFileActivityActionType::ACTOR_ADDED,
            [
                'actor_id' => $actor->getId(),
                'actor_name' => $actor->getLabel(),
                'actor_type' => $actorType->value,
                'explanation' => $explanation,
                'score' => $score,
                'primary_domain' => $actor->getPrimaryDomain(),
            ],
        );
    }

    public function logSourceAdd(WatchFile $watchFile, Source $source, User $addedBy): WatchFileActivity
    {
        return $this->create(
            $watchFile,
            $addedBy,
            WatchFileActivityActionType::SOURCE_ADDED,
            [
                'source_id' => $source->getId(),
                'source_name' => $source->getName(),
                'source_type' => $source->getType()
->value,
                'source_url' => $source->getUrl(),
                'primary_domain' => $source->getPrimaryDomain(),
                'actor_id' => $source->getActor()?->getId(),
                'actor_name' => $source->getActor()?->getLabel(),
            ],
        );
    }

    public function logDocumentQuotaExceeded(
        WatchFile $watchFile,
        User $user,
        int $documentCount,
        int $quota,
    ): WatchFileActivity {
        return $this->create(
            $watchFile,
            $user,
            WatchFileActivityActionType::DOCUMENT_QUOTA_EXCEEDED,
            [
                'document_count' => $documentCount,
                'quota' => $quota,
            ],
        );
    }

    /**
     * @param array<string, mixed> $actionData
     */
    private function create(
        WatchFile $watchFile,
        User $user,
        WatchFileActivityActionType $actionType,
        array $actionData,
    ): WatchFileActivity {
        return new WatchFileActivity(
            $watchFile,
            $user,
            $actionType,
            $actionData,
            $watchFile->getOrganisation(),
            $this->tenantContext->isInitialized() ? $this->tenantContext->getImpersonator() : null,
        );
    }
}
