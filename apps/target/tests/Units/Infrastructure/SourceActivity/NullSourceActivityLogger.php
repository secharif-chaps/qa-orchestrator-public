<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\SourceActivity;

use App\Domain\Collect\ApifyRunCost;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\SourceActivity\SourceActivity;
use App\Domain\SourceActivity\SourceActivityActionType;
use App\Domain\SourceActivity\SourceActivityLoggerInterface;
use App\Domain\User\User;

class NullSourceActivityLogger implements SourceActivityLoggerInterface
{
    /** @var list<array{source: Source, providerName: string, cost: ApifyRunCost, context: array<string, mixed>}> */
    private array $collectCostCalls = [];

    /**
     * @return list<array{source: Source, providerName: string, cost: ApifyRunCost, context: array<string, mixed>}>
     */
    public function getCollectCostCalls(): array
    {
        return $this->collectCostCalls;
    }

    public function countCollectCostCalls(): int
    {
        return \count($this->collectCostCalls);
    }

    public function logSourceConnected(
        Source $source,
        ?User $user,
        ?array $connectionData = null,
        ?string $providerName = null,
    ): SourceActivity {
        return new SourceActivity(
            $source,
            $user,
            SourceActivityActionType::SOURCE_CONNECTED,
            $connectionData ?? []
        );
    }

    public function logSourceDisconnected(
        Source $source,
        ?User $user,
        ?array $disconnectData = null,
        ?string $providerName = null,
    ): SourceActivity {
        return new SourceActivity(
            $source,
            $user,
            SourceActivityActionType::SOURCE_DISCONNECTED,
            $disconnectData ?? []
        );
    }

    public function logSourceError(
        Source $source,
        ?User $user,
        \Throwable $error,
        ?array $context = null,
        ?string $providerName = null,
    ): SourceActivity {
        return new SourceActivity(
            $source,
            $user,
            SourceActivityActionType::SOURCE_ERROR,
            [
                'error_message' => $error->getMessage(),
                'context' => $context ?? [],
            ]
        );
    }

    public function logSourceRecovered(
        Source $source,
        ?User $user,
        ?array $recoveryData = null,
        ?string $providerName = null,
    ): SourceActivity {
        return new SourceActivity($source, $user, SourceActivityActionType::SOURCE_RECOVERED, $recoveryData ?? []);
    }

    public function logSourceDataRetrieved(
        Source $source,
        ?User $user,
        int $dataCount,
        ?array $dataInfo = null,
        ?string $providerName = null,
    ): SourceActivity {
        return new SourceActivity(
            $source,
            $user,
            SourceActivityActionType::SOURCE_DATA_RETRIEVED,
            [
                'data_count' => $dataCount,
                'data_info' => $dataInfo ?? [],
            ]
        );
    }

    public function logSourceConfigUpdated(
        Source $source,
        ?User $user,
        array $changes,
        ?array $oldConfig = null,
    ): SourceActivity {
        return new SourceActivity(
            $source,
            $user,
            SourceActivityActionType::SOURCE_CONFIG_UPDATED,
            [
                'changes' => $changes,
                'old_config' => $oldConfig ?? [],
            ]
        );
    }

    public function logSourceStatusChanged(
        Source $source,
        ?User $user,
        SourceStatus $oldStatus,
        SourceStatus $newStatus,
        ?array $context = null,
    ): SourceActivity {
        return new SourceActivity(
            $source,
            $user,
            SourceActivityActionType::SOURCE_STATUS_CHANGED,
            [
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
                'context' => $context ?? [],
            ]
        );
    }

    public function logSourceAddedToWatchFile(Source $source, ?User $user, ?array $context = null): SourceActivity
    {
        return new SourceActivity(
            $source,
            $user,
            SourceActivityActionType::SOURCE_ADDED_TO_WATCHFILE,
            $context ?? []
        );
    }

    public function logSourceCollectStatusChanged(
        Source $source,
        ?User $user,
        CollectTaskStatus $oldStatus,
        CollectTaskStatus $newStatus,
        ?array $context = null,
    ): SourceActivity {
        return new SourceActivity(
            $source,
            $user,
            SourceActivityActionType::SOURCE_COLLECTOR_STATUS_CHANGED,
            [
                'old_collect_status' => $oldStatus->value,
                'new_collect_status' => $newStatus->value,
                'context' => $context ?? [],
            ]
        );
    }

    /**
     * @param array<string, mixed> $queryLogData
     */
    public function logSourceQueryLog(Source $source, array $queryLogData, ?string $providerName = null): SourceActivity
    {
        $actionData = $queryLogData;
        if (null !== $providerName) {
            $actionData['provider_name'] = $providerName;
        }

        return new SourceActivity($source, null, SourceActivityActionType::SOURCE_QUERY_LOG, $actionData);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function logSourceCollectCost(
        Source $source,
        string $providerName,
        ApifyRunCost $cost,
        array $context = [],
    ): SourceActivity {
        $this->collectCostCalls[] = [
            'source' => $source,
            'providerName' => $providerName,
            'cost' => $cost,
            'context' => $context,
        ];

        return new SourceActivity(
            $source,
            null,
            SourceActivityActionType::SOURCE_COLLECT_COST,
            [
                ...$context,
                'provider_name' => $providerName,
                'compute_units' => $cost->computeUnits,
                'cost_usd' => $cost->costUsd,
            ]
        );
    }
}
