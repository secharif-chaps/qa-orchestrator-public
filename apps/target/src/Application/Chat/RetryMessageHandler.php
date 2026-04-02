<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Domain\AI\ChatSessionInterface;
use App\Domain\Chat\ConversationGatewayInterface;
use App\Domain\Chat\ConversationState;
use App\Domain\Chat\MaxRetryAttemptsExceededException;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageCannotBeRetriedException;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Chat\MessageStatus;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\User\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
readonly class RetryMessageHandler
{
    public const int MAX_RETRY_ATTEMPTS = 3;

    public function __construct(
        private MessageGatewayInterface $messageGateway,
        private ConversationGatewayInterface $conversationGateway,
        private ChatSessionInterface $chatSession,
        private RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
        private Security $security,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(RetryMessageAction $action): Message
    {
        $message = $this->messageGateway->get($action->messageId->toRfc4122());

        // Update conversation state to waiting for agent
        $conversation = $message->getConversation();
        if (null === $conversation) {
            $this->logger?->warning(
                'Message retry attempted for message without conversation',
                [
                    'message_id' => $message->getId(),
                ],
            );
            throw new \RuntimeException('Cannot retry message without a conversation');
        }

        $this->validateMessageCanBeRetried($message);

        // Increment retry count and set status to pending
        $message->incrementRetryCount();
        $message->setStatus(MessageStatus::Pending);

        // Track who performed the retry
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $message->updateBy($user);
        }

        $conversation->setState(ConversationState::WaitingForAgent);
        $this->conversationGateway->save($conversation);
        $this->messageGateway->save($message);

        // Notify UI of status change
        $this->realTimeUpdatePublisher->publishMessageUpdate($message);
        $this->realTimeUpdatePublisher->publishConversationUpdate($conversation);

        // Re-dispatch message to N8N
        $this->chatSession->sendMessage($conversation, $message);

        $this->logger?->info('Message retry initiated', [
            'message_id' => $message->getId(),
            'retry_count' => $message->getRetryCount(),
            'conversation_id' => $conversation->getId(),
        ]);

        return $message;
    }

    private function validateMessageCanBeRetried(Message $message): void
    {
        $messageId = $message->getId();
        Assert::notNull($messageId, 'messageId cannot be null');

        if (!$message->getStatus()->isError()) {
            throw new MessageCannotBeRetriedException($messageId, $message->getStatus());
        }

        if ($message->getRetryCount() >= self::MAX_RETRY_ATTEMPTS) {
            throw new MaxRetryAttemptsExceededException($messageId, self::MAX_RETRY_ATTEMPTS);
        }
    }
}
