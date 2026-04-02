<?php

declare(strict_types=1);

namespace App\Infrastructure\SourceActivity;

use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\SourceActivity\SourceActivity;
use App\Domain\SourceActivity\SourceActivityActionType;
use App\Domain\SourceActivity\SourceActivityLoggerInterface;
use App\Domain\User\User;

class SourceActivityLogger implements SourceActivityLoggerInterface
{
    /**
     * @param array<string, mixed>|null $connectionData
     */
    public function logSourceConnected(
        Source $source,
        ?User $user,
        ?array $connectionData = null,
        ?string $providerName = null,
    ): SourceActivity {
        $actionData = [
            'source_id' => $source->getId(),
            'source_name' => $source->getName(),
            'source_type' => $source->getType()
                ->value,
            'source_url' => $source->getUrl(),
            'connection_data' => $connectionData ?? [],
            'timestamp' => new \DateTime()
                ->format('c'),
        ];

        if (null !== $providerName) {
            $actionData['provider_name'] = $providerName;
        }

        return new SourceActivity($source, $user, SourceActivityActionType::SOURCE_CONNECTED, $actionData);
    }

    /**
     * @param array<string, mixed>|null $disconnectData
     */
    public function logSourceDisconnected(
        Source $source,
        ?User $user,
        ?array $disconnectData = null,
        ?string $providerName = null,
    ): SourceActivity {
        $actionData = [
            'source_id' => $source->getId(),
            'source_name' => $source->getName(),
            'source_type' => $source->getType()
                ->value,
            'source_url' => $source->getUrl(),
            'disconnect_data' => $disconnectData ?? [],
            'timestamp' => new \DateTime()
                ->format('c'),
        ];

        if (null !== $providerName) {
            $actionData['provider_name'] = $providerName;
        }

        return new SourceActivity($source, $user, SourceActivityActionType::SOURCE_DISCONNECTED, $actionData);
    }

    /**
     * @param array<string, mixed>|null $context
     */
    public function logSourceError(
        Source $source,
        ?User $user,
        \Throwable $error,
        ?array $context = null,
        ?string $providerName = null,
    ): SourceActivity {
        $actionData = [
            'source_id' => $source->getId(),
            'source_name' => $source->getName(),
            'source_type' => $source->getType()
                ->value,
            'source_url' => $source->getUrl(),
            'error_message' => $error->getMessage(),
            'error_code' => $error->getCode(),
            'error_file' => $error->getFile(),
            'error_line' => $error->getLine(),
            'context' => $context ?? [],
            'timestamp' => new \DateTime()
                ->format('c'),
        ];

        if (null !== $providerName) {
            $actionData['provider_name'] = $providerName;
        }

        return new SourceActivity($source, $user, SourceActivityActionType::SOURCE_ERROR, $actionData);
    }

    public function logSourceRecovered(
        Source $source,
        ?User $user,
        ?array $recoveryData = null,
        ?string $providerName = null,
    ): SourceActivity {
        $actionData = [
            'source_id' => $source->getId(),
            'source_name' => $source->getName(),
            'source_type' => $source->getType()
                ->value,
            'source_url' => $source->getUrl(),
            'recovery_data' => $recoveryData ?? [],
            'timestamp' => new \DateTime()
                ->format('c'),
        ];

        if (null !== $providerName) {
            $actionData['provider_name'] = $providerName;
        }

        return new SourceActivity($source, $user, SourceActivityActionType::SOURCE_RECOVERED, $actionData);
    }

    public function logSourceDataRetrieved(
        Source $source,
        ?User $user,
        int $dataCount,
        ?array $dataInfo = null,
        ?string $providerName = null,
    ): SourceActivity {
        $actionData = [
            'source_id' => $source->getId(),
            'source_name' => $source->getName(),
            'source_type' => $source->getType()
                ->value,
            'source_url' => $source->getUrl(),
            'data_count' => $dataCount,
            'data_info' => $dataInfo ?? [],
            'timestamp' => new \DateTime()
                ->format('c'),
        ];

        if (null !== $providerName) {
            $actionData['provider_name'] = $providerName;
        }

        return new SourceActivity($source, $user, SourceActivityActionType::SOURCE_DATA_RETRIEVED, $actionData);
    }

    /**
     * @param array<string, mixed>      $changes
     * @param array<string, mixed>|null $oldConfig
     */
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
                'source_id' => $source->getId(),
                'source_name' => $source->getName(),
                'source_type' => $source->getType()
                    ->value,
                'source_url' => $source->getUrl(),
                'changes' => $changes,
                'old_config' => $oldConfig ?? [],
                'timestamp' => new \DateTime()
                    ->format('c'),
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
                'source_id' => $source->getId(),
                'source_name' => $source->getName(),
                'source_type' => $source->getType()
                    ->value,
                'source_url' => $source->getUrl(),
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
                'context' => $context ?? [],
                'timestamp' => new \DateTime()
                    ->format('c'),
            ]
        );
    }

    public function logSourceAddedToWatchFile(Source $source, ?User $user, ?array $context = null): SourceActivity
    {
        return new SourceActivity(
            $source,
            $user,
            SourceActivityActionType::SOURCE_ADDED_TO_WATCHFILE,
            [
                'source_id' => $source->getId(),
                'source_name' => $source->getName(),
                'source_type' => $source->getType()
                    ->value,
                'source_url' => $source->getUrl(),
                'watch_file_name' => $source->getWatchFile()
                    ->getName(),
                'context' => $context ?? [],
                'timestamp' => new \DateTime()
                    ->format('c'),
            ]
        );
    }

    public function logSourceCollectStatusChanged(
        Source $source,
        ?User $user,
        CollectTaskStatus $oldStatus,
        CollectTaskStatus $newStatus,
        ?array $context = null,
    ): SourceActivity {
        $actionData = [
            'source_id' => $source->getId(),
            'source_name' => $source->getName(),
            'source_type' => $source->getType()
                ->value,
            'source_url' => $source->getUrl(),
            'old_collect_status' => $oldStatus->value,
            'new_collect_status' => $newStatus->value,
            'context' => $context ?? [],
            'timestamp' => new \DateTime()
                ->format('c'),
        ];

        if (isset($context['provider_name'])) {
            $actionData['provider_name'] = $context['provider_name'];
        }

        return new SourceActivity(
            $source,
            $user,
            SourceActivityActionType::SOURCE_COLLECTOR_STATUS_CHANGED,
            $actionData
        );
    }

    /**
     * @param array<string, mixed> $queryLogData
     */
    public function logSourceQueryLog(Source $source, array $queryLogData, ?string $providerName = null): SourceActivity
    {
        $actionData = [
            'source_id' => $source->getId(),
            'source_name' => $source->getName(),
            'source_type' => $source->getType()
                ->value,
            'source_url' => $source->getUrl(),
            'query_log' => $queryLogData,
            'timestamp' => new \DateTime()
                ->format('c'),
        ];

        if (null !== $providerName) {
            $actionData['provider_name'] = $providerName;
        }

        return new SourceActivity($source, null, SourceActivityActionType::SOURCE_QUERY_LOG, $actionData);
    }
}
