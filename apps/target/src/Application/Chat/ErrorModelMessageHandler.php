<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Domain\Agent\AgentExecutionGatewayInterface;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\ConversationGatewayInterface;
use App\Domain\Chat\ConversationState;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Chat\MessageNotFoundException;
use App\Domain\Chat\MessageRole;
use App\Domain\Chat\MessageStatus;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
readonly class ErrorModelMessageHandler
{
    public function __construct(
        private RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
        private ConversationGatewayInterface $conversationGateway,
        private AgentExecutionGatewayInterface $agentExecutionGateway,
        private MessageGatewayInterface $messageGateway,
        private LocaleSwitcher $localeSwitcher,
        private TranslatorInterface $translator,
        #[Autowire('%env(int:AGENT_EXECUTION_TIMEOUT_SECONDS)%')]
        private int $timeoutSeconds,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ErrorModelMessageAction $action): void
    {
        if (null === $action->conversationId) {
            $conversation = $this->conversationGateway->getLastConversationByWatchFileId($action->watchFileId);
        } else {
            $conversation = $this->conversationGateway->get($action->conversationId);
        }

        if (ConversationState::Idle === $conversation->getState()) {
            $this->logger?->info('Discarding error message for idle conversation (likely cancelled)', [
                'conversation_id' => $conversation->getId(),
            ]);

            return;
        }

        $agentExecution = $conversation->getAgentExecution();
        if (null !== $agentExecution && $agentExecution->getStatus()->isTerminal()) {
            $this->logger?->info('Discarding error message for terminated execution (likely timed out)', [
                'conversation_id' => $conversation->getId(),
                'execution_status' => $agentExecution
                    ->getStatus()
                    ->value,
            ]);

            return;
        }

        // If we have a messageId, update the original message status to error
        if (null !== $action->messageId) {
            try {
                $originalMessage = $this->messageGateway->get($action->messageId);
                $originalMessage->setStatus(MessageStatus::Error);
                $this->messageGateway->save($originalMessage);

                $this->realTimeUpdatePublisher->publishMessageUpdate($originalMessage);
            } catch (MessageNotFoundException) {
                // Message not found, continue with error message creation
            }
        }

        $this->handleErrorMessageCreation($action, $conversation);
    }

    private function handleErrorMessageCreation(ErrorModelMessageAction $action, Conversation $conversation): void
    {
        $agentExecution = $conversation->getAgentExecution();
        $isTimeout = null !== $agentExecution
            && new \DateTimeImmutable()
                ->getTimestamp() - $agentExecution->getStartedAt()
                ->getTimestamp() >= $this->timeoutSeconds;

        $message = new Message();

        if (!empty($action->error)) {
            $message->setTextContent($action->error);
            $this->logger?->error('Model error message received', [
                'conversation_id' => $conversation->getId(),
                'error' => $action->error,
                'is_timeout' => $isTimeout,
            ]);
        } else {
            $this->logger?->error('Model error message received without error details', [
                'conversation_id' => $conversation->getId(),
                'is_timeout' => $isTimeout,
            ]);

            $this->localeSwitcher->runWithLocale(
                $conversation->getLanguage(),
                function () use ($message, $isTimeout): void {
                    $message->setTextContent(
                        $isTimeout
                            ? $this->translator->trans('conversation.timed_out')
                            : $this->translator->trans('model.error_message')
                    );
                },
            );
        }

        $message->setMetadata([
            'reason' => $isTimeout ? 'timed_out' : 'error',
            'error' => $action->error,
            'messageId' => $action->messageId,
            'time' => $action->errorTime,
            'context' => $action->context,
        ]);
        $message->setRole(MessageRole::SystemError);
        $message->setStatus(MessageStatus::Delivered);

        // Update conversation state to idle (processing complete, albeit with error)
        $conversation->setState(ConversationState::Idle);

        // Mark agent execution as timed out or failed, and detach from conversation
        if (null !== $agentExecution && !$agentExecution->getStatus()->isTerminal()) {
            $isTimeout ? $agentExecution->timeout() : $agentExecution->fail();
            $this->agentExecutionGateway->save($agentExecution);
        }
        $conversation->unlinkAgentExecution();

        $conversation->addMessage($message);
        $this->conversationGateway->save($conversation);

        $this->realTimeUpdatePublisher->publishMessageUpdate($message);
        $this->realTimeUpdatePublisher->publishConversationUpdate($conversation);
    }
}
