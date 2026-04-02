<?php

declare(strict_types=1);

namespace App\Domain\AI;

use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;

interface ChatSessionInterface
{
    public function sendMessage(Conversation $conversation, Message $message): Message;
}
