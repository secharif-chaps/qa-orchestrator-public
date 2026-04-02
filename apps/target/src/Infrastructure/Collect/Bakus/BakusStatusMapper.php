<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Bakus;

use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\StatusMapperInterface;

class BakusStatusMapper implements StatusMapperInterface
{
    public function mapStatus(string $providerStatus): CollectTaskStatus
    {
        return match ($providerStatus) {
            'pending' => CollectTaskStatus::QUEUED,
            'in_progress' => CollectTaskStatus::RUNNING,
            'done' => CollectTaskStatus::COMPLETED,
            'canceled', 'deleted', 'cancelled' => CollectTaskStatus::CANCELLED,
            default => CollectTaskStatus::FAILED,
        };
    }
}
