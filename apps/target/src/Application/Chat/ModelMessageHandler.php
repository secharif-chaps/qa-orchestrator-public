<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Domain\Agent\AgentExecutionGatewayInterface;
use App\Domain\AI\LlmOutputSanitizerInterface;
use App\Domain\Chat\ConversationGatewayInterface;
use App\Domain\Chat\ConversationState;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Chat\MessageRole;
use App\Domain\Chat\MessageStatus;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ModelMessageHandler
{
    public function __construct(
        private ConversationGatewayInterface $conversationGateway,
        private AgentExecutionGatewayInterface $agentExecutionGateway,
        private MessageGatewayInterface $messageGateway,
        private RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
        private LlmOutputSanitizerInterface $llmOutputSanitizer,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ModelMessageAction $action): void
    {
        $conversation = $this->conversationGateway->get($action->conversationId);

        if (ConversationState::Idle === $conversation->getState()) {
            $this->logger?->info('Discarding model message for idle conversation (likely cancelled)', [
                'conversation_id' => $action->conversationId,
            ]);

            return;
        }

        $agentExecution = $conversation->getAgentExecution();
        if (null !== $agentExecution && $agentExecution->getStatus()->isTerminal()) {
            $this->logger?->info('Discarding model message for terminated execution (likely timed out)', [
                'conversation_id' => $action->conversationId,
                'execution_status' => $agentExecution->getStatus()
->value,
            ]);

            return;
        }

        if (!$action->isValidMessage()) {
            $this->logger?->warning('Unable to retrieve message from model response');

            return;
        }

        $sanitizedContent = $this->llmOutputSanitizer->sanitize($action->getTrimmedMessage());

        // Create model response message with delivered status
        $message = new Message();
        $message->setTextContent($sanitizedContent);
        $message->setRole(MessageRole::Model);
        $message->setConversation($conversation);
        $message->setStatus(MessageStatus::Delivered);
        $message->setMetadata($action->context);

        // Update conversation state to idle (processing complete)
        $conversation->setState(ConversationState::Idle);

        // Complete agent execution and detach from conversation
        $agentExecution = $conversation->getAgentExecution();
        if (null !== $agentExecution && !$agentExecution->getStatus()->isTerminal()) {
            $agentExecution->complete();
            $this->agentExecutionGateway->save($agentExecution);
        }
        $conversation->unlinkAgentExecution();

        $this->messageGateway->save($message);
        $this->conversationGateway->save($conversation);

        $this->realTimeUpdatePublisher->publishMessageUpdate($message);
        $this->realTimeUpdatePublisher->publishConversationUpdate($conversation);

        $this->logger?->info('Model message added to conversation', [
            'conversation_id' => $conversation->getId(),
            'message_id' => $message->getId(),
        ]);
    }
}
