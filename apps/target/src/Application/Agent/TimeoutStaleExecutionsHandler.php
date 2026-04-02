<?php

declare(strict_types=1);

namespace App\Application\Agent;

use App\Domain\Agent\AgentExecution;
use App\Domain\Agent\AgentExecutionCancellerInterface;
use App\Domain\Agent\AgentExecutionGatewayInterface;
use App\Domain\Chat\ConversationGatewayInterface;
use App\Domain\Chat\ConversationState;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Chat\MessageRole;
use App\Domain\Chat\MessageStatus;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
readonly class TimeoutStaleExecutionsHandler
{
    public function __construct(
        private AgentExecutionGatewayInterface $agentExecutionGateway,
        private AgentExecutionCancellerInterface $executionCanceller,
        private ConversationGatewayInterface $conversationGateway,
        private MessageGatewayInterface $messageGateway,
        private RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
        private LocaleSwitcher $localeSwitcher,
        private TranslatorInterface $translator,
        #[Autowire('%env(int:AGENT_EXECUTION_TIMEOUT_SECONDS)%')]
        private int $timeoutSeconds,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(TimeoutStaleExecutionsAction $action): int
    {
        $staleExecutions = $this->agentExecutionGateway->findStaleExecutions($this->timeoutSeconds);
        $count = 0;

        foreach ($staleExecutions as $execution) {
            $this->timeoutExecution($execution);
            ++$count;
        }

        if ($count > 0) {
            $this->logger?->info('Timed out stale executions', [
                'count' => $count,
            ]);
        }

        return $count;
    }

    private function timeoutExecution(AgentExecution $execution): void
    {
        // Mark as timed_out FIRST to prevent race condition with ModelMessageHandler
        $execution->timeout();
        $this->agentExecutionGateway->save($execution);

        try {
            $this->executionCanceller->cancel($execution->getExecutionId());
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to cancel n8n execution, continuing with timeout handling', [
                'execution_id' => $execution->getExecutionId(),
                'error' => $e->getMessage(),
            ]);
        }

        $conversation = $this->conversationGateway->findByAgentExecution($execution);
        if (null === $conversation) {
            return;
        }

        $conversation->setState(ConversationState::Idle);

        $message = new Message();
        $message->setRole(MessageRole::SystemError);
        $message->setStatus(MessageStatus::Delivered);
        $message->setMetadata([
            'reason' => 'timed_out',
        ]);

        $this->localeSwitcher->runWithLocale(
            $conversation->getLanguage(),
            function () use ($message): void {
                $message->setTextContent($this->translator->trans('conversation.timed_out', [], 'messages'));
            },
        );

        $conversation->addMessage($message);
        $this->messageGateway->save($message);
        $this->conversationGateway->save($conversation);

        $this->realTimeUpdatePublisher->publishMessageUpdate($message);
        $this->realTimeUpdatePublisher->publishConversationUpdate($conversation);
    }
}
