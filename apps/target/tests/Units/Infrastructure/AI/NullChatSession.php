<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\AI;

use App\Domain\AI\ChatSessionInterface;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;

class NullChatSession implements ChatSessionInterface
{
    private ?Message $lastSentMessage = null;
    private ?Conversation $lastSentConversation = null;

    public function sendMessage(Conversation $conversation, Message $message): Message
    {
        $this->lastSentMessage = $message;
        $this->lastSentConversation = $conversation;

        $response = new Message();
        $response->setTextContent('AI response');

        return $response;
    }

    public function getLastSentMessage(): ?Message
    {
        return $this->lastSentMessage;
    }

    public function getLastSentConversation(): ?Conversation
    {
        return $this->lastSentConversation;
    }
}
