<?php

declare(strict_types=1);

namespace App\Application\Collect\Auth;

use App\Application\SyncActionInterface;

readonly class GenerateCollectTaskTokenAction implements SyncActionInterface
{
    public function __construct(
        public string $collectTaskId,
    ) {
    }
}
