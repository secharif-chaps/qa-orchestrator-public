<?php

declare(strict_types=1);

namespace App\Application\Collect;

use App\Application\SyncActionInterface;

/**
 * Initiates an outbound WebSocket connection to pull streaming data from the provider.
 *
 * This action represents the "Pull" mode where our application acts as a WebSocket client
 * connecting to the provider's WebSocket server to receive real-time collect task data.
 */
readonly class PullStreamCollectTaskDataAction implements SyncActionInterface
{
    public function __construct(
        public string $collectTaskId,
    ) {
    }
}
