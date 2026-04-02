<?php

declare(strict_types=1);

namespace App\Domain\WatchFileActivity;

use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorStatus;
use App\Domain\Actor\ActorType;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\User\User;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUser;

interface WatchFileActivityLoggerInterface
{
    public function logCreate(WatchFile $watchFile, User $user): WatchFileActivity;

    /**
     * @param array<string,array{old:mixed,new:mixed}> $changes
     * @param array<string, mixed>|null                $context
     */
    public function logUpdate(
        WatchFile $watchFile,
        User $user,
        array $changes,
        ?array $context = null,
    ): WatchFileActivity;

    public function logSourceStatusChange(
        Source $source,
        WatchFile $watchFile,
        User $user,
        SourceStatus $status,
        SourceStatus $oldStatus,
    ): WatchFileActivity;

    public function logStatusChange(
        WatchFile $watchFile,
        User $user,
        WatchFileStatus $oldStatus,
        WatchFileStatus $status,
    ): WatchFileActivity;

    public function logActorStatusChange(
        Actor $actor,
        WatchFile $watchFile,
        User $user,
        ActorStatus $status,
        ActorStatus $oldStatus,
    ): WatchFileActivity;

    /**
     * @param array<string, mixed> $classificationData
     */
    public function logMonitoringTypeDetection(
        WatchFile $watchFile,
        User $user,
        MonitoringType $detectedType,
        array $classificationData,
    ): WatchFileActivity;

    /**
     * @param array<string, mixed> $context
     */
    public function logReferenceSubjectUpdate(
        WatchFile $watchFile,
        User $user,
        ?TranslatedText $oldReferenceSubject,
        TranslatedText $newReferenceSubject,
        ?array $context = null,
    ): WatchFileActivity;

    public function logShare(
        WatchFile $watchFile,
        WatchFileUser $watchFileUser,
        User $sharedBy,
    ): WatchFileActivity;

    public function logUnshare(
        WatchFile $watchFile,
        WatchFileUser $watchFileUser,
        User $removedBy,
    ): WatchFileActivity;

    public function logActorAdd(
        WatchFile $watchFile,
        Actor $actor,
        User $addedBy,
        ActorType $actorType,
        ?TranslatedText $explanation = null,
        ?float $score = null,
    ): WatchFileActivity;

    public function logSourceAdd(WatchFile $watchFile, Source $source, User $addedBy): WatchFileActivity;

    public function logDocumentQuotaExceeded(
        WatchFile $watchFile,
        User $user,
        int $documentCount,
        int $quota,
    ): WatchFileActivity;
}
