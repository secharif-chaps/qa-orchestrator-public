<?php

declare(strict_types=1);

namespace App\Application\Collect;

use App\Application\SyncActionInterface;

/**
 * Initiates an incoming WebSocket connection to receive real-time collect task data.
 *
 * This action represents the "Push" mode where the data provider pushes data to our application
 * via a WebSocket connection.
 */
readonly class PushStreamCollectTaskDataAction implements SyncActionInterface
{
}
