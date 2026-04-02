<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Agent;

use App\Application\Agent\AgentAcknowledgeAction;
use App\Application\Agent\AgentAcknowledgeHandler;
use App\Domain\Agent\AgentExecutionStatus;
use App\Domain\Chat\Conversation;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Agent\NullAgentExecutionGateway;
use App\Tests\Units\Infrastructure\Chat\NullConversationGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AgentAcknowledgeHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private NullAgentExecutionGateway $agentExecutionGateway;
    private NullConversationGateway $conversationGateway;
    private RealTimeUpdatePublisherInterface&MockObject $realTimeUpdatePublisher;
    private AgentAcknowledgeHandler $handler;

    protected function setUp(): void
    {
        $this->agentExecutionGateway = new NullAgentExecutionGateway();
        $this->conversationGateway = new NullConversationGateway();
        $this->realTimeUpdatePublisher = $this->createMock(RealTimeUpdatePublisherInterface::class);
        $this->handler = new AgentAcknowledgeHandler(
            $this->agentExecutionGateway,
            $this->conversationGateway,
            $this->realTimeUpdatePublisher,
            new NullLogger(),
        );
    }

    public function testAcknowledgeWithConversation(): void
    {
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');
        $this->conversationGateway->addConversation($conversation);

        $action = new AgentAcknowledgeAction(
            executionId: 'exec-456',
            commandName: 'ChatSessionMessage',
            conversationId: 'conversation-id',
        );

        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishConversationUpdate')
            ->with($conversation);

        ($this->handler)($action);

        $savedExecution = $this->agentExecutionGateway->savedExecution;
        $this->assertNotNull($savedExecution);
        $this->assertSame('exec-456', $savedExecution->getExecutionId());
        $this->assertSame('ChatSessionMessage', $savedExecution->getCommandName());
        $this->assertSame(AgentExecutionStatus::Running, $savedExecution->getStatus());

        $this->assertSame($savedExecution, $conversation->getAgentExecution());
        $this->assertSame($conversation, $this->conversationGateway->savedConversation);
    }

    public function testAcknowledgeWithoutConversation(): void
    {
        $action = new AgentAcknowledgeAction(executionId: 'exec-789', commandName: 'DocumentSummary');

        $this->realTimeUpdatePublisher
            ->expects($this->never())
            ->method('publishConversationUpdate');

        ($this->handler)($action);

        $savedExecution = $this->agentExecutionGateway->savedExecution;
        $this->assertNotNull($savedExecution);
        $this->assertSame('exec-789', $savedExecution->getExecutionId());
        $this->assertSame('DocumentSummary', $savedExecution->getCommandName());
        $this->assertSame(AgentExecutionStatus::Running, $savedExecution->getStatus());

        $this->assertNull($this->conversationGateway->savedConversation);
    }
}
