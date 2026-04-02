<?php

declare(strict_types=1);

namespace App\Application\Collect\Auth;

use App\Application\SyncActionInterface;

readonly class AuthenticateAction implements SyncActionInterface
{
    public function __construct(
        public string $provider,
        public bool $forceRefresh = false,
    ) {
    }
}
