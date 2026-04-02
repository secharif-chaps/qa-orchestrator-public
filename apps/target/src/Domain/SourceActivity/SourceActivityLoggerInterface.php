<?php

declare(strict_types=1);

namespace App\Domain\SourceActivity;

use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\User\User;

interface SourceActivityLoggerInterface
{
    /**
     * @param array<string, mixed>|null $connectionData
     */
    public function logSourceConnected(
        Source $source,
        ?User $user,
        ?array $connectionData = null,
        ?string $providerName = null,
    ): SourceActivity;

    /**
     * @param array<string, mixed>|null $disconnectData
     */
    public function logSourceDisconnected(
        Source $source,
        ?User $user,
        ?array $disconnectData = null,
        ?string $providerName = null,
    ): SourceActivity;

    /**
     * @param array<string, mixed>|null $context
     */
    public function logSourceError(
        Source $source,
        ?User $user,
        \Throwable $error,
        ?array $context = null,
        ?string $providerName = null,
    ): SourceActivity;

    /**
     * @param array<string, mixed>|null $recoveryData
     */
    public function logSourceRecovered(
        Source $source,
        ?User $user,
        ?array $recoveryData = null,
        ?string $providerName = null,
    ): SourceActivity;

    /**
     * @param array<string, mixed>|null $dataInfo
     */
    public function logSourceDataRetrieved(
        Source $source,
        ?User $user,
        int $dataCount,
        ?array $dataInfo = null,
        ?string $providerName = null,
    ): SourceActivity;

    /**
     * @param array<string, mixed>      $changes
     * @param array<string, mixed>|null $oldConfig
     */
    public function logSourceConfigUpdated(
        Source $source,
        ?User $user,
        array $changes,
        ?array $oldConfig = null,
    ): SourceActivity;

    /**
     * @param array<string, mixed>|null $context
     */
    public function logSourceStatusChanged(
        Source $source,
        ?User $user,
        SourceStatus $oldStatus,
        SourceStatus $newStatus,
        ?array $context = null,
    ): SourceActivity;

    /**
     * @param array<string, mixed>|null $context
     */
    public function logSourceAddedToWatchFile(Source $source, ?User $user, ?array $context = null): SourceActivity;

    /**
     * @param array<string, mixed>|null $context
     */
    public function logSourceCollectStatusChanged(
        Source $source,
        ?User $user,
        CollectTaskStatus $oldStatus,
        CollectTaskStatus $newStatus,
        ?array $context = null,
    ): SourceActivity;

    /**
     * @param array<string, mixed> $queryLogData
     */
    public function logSourceQueryLog(
        Source $source,
        array $queryLogData,
        ?string $providerName = null,
    ): SourceActivity;
}
