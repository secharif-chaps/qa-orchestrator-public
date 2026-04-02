<?php

declare(strict_types=1);

namespace App\Application\Agent;

use App\Domain\Agent\AgentExecution;
use App\Domain\Agent\AgentExecutionGatewayInterface;
use App\Domain\Chat\ConversationGatewayInterface;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class AgentAcknowledgeHandler
{
    public function __construct(
        private AgentExecutionGatewayInterface $agentExecutionGateway,
        private ConversationGatewayInterface $conversationGateway,
        private RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(AgentAcknowledgeAction $action): void
    {
        $agentExecution = new AgentExecution($action->executionId, $action->commandName);
        $this->agentExecutionGateway->save($agentExecution);

        $this->logger?->info('Agent execution acknowledged', [
            'execution_id' => $action->executionId,
            'command_name' => $action->commandName,
        ]);

        if (null === $action->conversationId) {
            return;
        }

        $conversation = $this->conversationGateway->get($action->conversationId);
        $conversation->linkAgentExecution($agentExecution);
        $this->conversationGateway->save($conversation);

        $this->realTimeUpdatePublisher->publishConversationUpdate($conversation);

        $this->logger?->info('Agent execution linked to conversation', [
            'execution_id' => $action->executionId,
            'conversation_id' => $action->conversationId,
        ]);
    }
}
