<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify;

use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\StatusMapperInterface;

class ApifyStatusMapper implements StatusMapperInterface
{
    public function mapStatus(string $providerStatus): CollectTaskStatus
    {
        return match ($providerStatus) {
            'READY' => CollectTaskStatus::QUEUED,
            'RUNNING' => CollectTaskStatus::RUNNING,
            'TIMING-OUT' => CollectTaskStatus::RUNNING,
            'ABORTING' => CollectTaskStatus::RUNNING,
            'SUCCEEDED' => CollectTaskStatus::COMPLETED,
            'FAILED' => CollectTaskStatus::FAILED,
            'TIMED-OUT' => CollectTaskStatus::FAILED,
            'ABORTED' => CollectTaskStatus::CANCELLED,
            default => CollectTaskStatus::FAILED,
        };
    }
}
