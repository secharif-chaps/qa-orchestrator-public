<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use App\Application\SyncActionInterface;
use Symfony\Component\Uid\Uuid;

readonly class CheckWatchFileOwnerQuotaAction implements SyncActionInterface
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
