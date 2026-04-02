<?php

declare(strict_types=1);

namespace App\Domain\Chat\Event;

use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use Symfony\Contracts\EventDispatcher\Event;

class MessageReceivedEvent extends Event
{
    public const string NAME = 'chat.message_received';

    public function __construct(
        public readonly Conversation $conversation,
        public readonly Message $message,
    ) {
    }
}
