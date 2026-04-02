<?php

declare(strict_types=1);

namespace App\Application\Mercure;

use App\Application\SyncActionInterface;

final readonly class GenerateTokenAction implements SyncActionInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
