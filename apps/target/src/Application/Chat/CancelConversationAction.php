<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Application\SyncActionInterface;

readonly class CancelConversationAction implements SyncActionInterface
{
    public function __construct(
        public string $conversationId,
    ) {
    }
}
