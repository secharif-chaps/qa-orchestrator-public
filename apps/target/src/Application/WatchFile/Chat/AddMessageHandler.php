<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Chat;

use App\Domain\AI\ChatSessionInterface;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\ConversationGatewayInterface;
use App\Domain\Chat\ConversationState;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Chat\MessageStatus;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\WatchFile\WatchFileActiveException;
use App\Domain\WatchFile\WatchFileStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class AddMessageHandler
{
    public function __construct(
        private ConversationGatewayInterface $conversationGateway,
        private MessageGatewayInterface $messageGateway,
        private ChatSessionInterface $chatSession,
        private RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
    ) {
    }

    public function __invoke(AddMessageAction $action): Conversation
    {
        $conversation = $this->conversationGateway->get($action->conversationId);

        $watchFile = $conversation->getWatchFile();
        if (WatchFileStatus::ENABLED === $watchFile->getStatus()) {
            throw new WatchFileActiveException($watchFile->getId(), 'add messages to conversation');
        }

        // Create message with pending status initially
        $message = new Message($conversation)
            ->setTextContent($action->message)
            ->setStatus(MessageStatus::Pending);

        // Update conversation state to waiting for agent
        $conversation->setState(ConversationState::WaitingForAgent);

        $this->messageGateway->save($message);
        $this->conversationGateway->save($conversation);

        // Dispatch to N8N - status changes to "sent" after successful dispatch
        $this->chatSession->sendMessage($conversation, $message);

        // Update status to sent after successful dispatch
        $message->setStatus(MessageStatus::Sent);
        $this->messageGateway->save($message);

        $this->realTimeUpdatePublisher->publishMessageUpdate($message);

        return $conversation;
    }
}
