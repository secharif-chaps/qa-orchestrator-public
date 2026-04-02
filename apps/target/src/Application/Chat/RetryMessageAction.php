<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Application\SyncActionInterface;
use Symfony\Component\Uid\Uuid;

readonly class RetryMessageAction implements SyncActionInterface
{
    public function __construct(
        public Uuid $messageId,
    ) {
    }
}
