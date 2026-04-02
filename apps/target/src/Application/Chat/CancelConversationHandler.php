<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Domain\Agent\AgentExecutionCancellerInterface;
use App\Domain\Agent\AgentExecutionGatewayInterface;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\ConversationGatewayInterface;
use App\Domain\Chat\ConversationState;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Chat\MessageRole;
use App\Domain\Chat\MessageStatus;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
readonly class CancelConversationHandler
{
    public function __construct(
        private ConversationGatewayInterface $conversationGateway,
        private AgentExecutionGatewayInterface $agentExecutionGateway,
        private AgentExecutionCancellerInterface $executionCanceller,
        private MessageGatewayInterface $messageGateway,
        private RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
        private LocaleSwitcher $localeSwitcher,
        private TranslatorInterface $translator,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(CancelConversationAction $action): Conversation
    {
        $conversation = $this->conversationGateway->get($action->conversationId);

        $agentExecution = $conversation->getAgentExecution();
        if (null !== $agentExecution && !$agentExecution->getStatus()->isTerminal()) {
            $this->executionCanceller->cancel($agentExecution->getExecutionId());
            $agentExecution->cancel();
            $this->agentExecutionGateway->save($agentExecution);
        }

        $conversation->setState(ConversationState::Idle);

        $this->localeSwitcher->runWithLocale(
            $conversation->getLanguage(),
            function () use ($conversation): void {
                $message = new Message();
                $message->setTextContent($this->translator->trans('conversation.cancelled', [], 'messages'));
                $message->setRole(MessageRole::SystemError);
                $message->setStatus(MessageStatus::Delivered);
                $message->setMetadata([
                    'reason' => 'cancelled',
                ]);
                $conversation->addMessage($message);
                $this->messageGateway->save($message);
            },
        );

        $this->conversationGateway->save($conversation);

        $lastMessage = $conversation->getMessages()
->last();
        if ($lastMessage instanceof Message) {
            $this->realTimeUpdatePublisher->publishMessageUpdate($lastMessage);
        }
        $this->realTimeUpdatePublisher->publishConversationUpdate($conversation);

        $this->logger?->info('Conversation cancelled', [
            'conversation_id' => $action->conversationId,
        ]);

        return $conversation;
    }
}
