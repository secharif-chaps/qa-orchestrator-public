<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Chat;

use App\Application\SyncActionInterface;

readonly class CreateConversationAction implements SyncActionInterface
{
    public function __construct(
        public string $watchFileId,
        public string $message,
    ) {
    }
}
