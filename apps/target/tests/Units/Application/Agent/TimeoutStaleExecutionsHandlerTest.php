<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Agent;

use App\Application\Agent\TimeoutStaleExecutionsAction;
use App\Application\Agent\TimeoutStaleExecutionsHandler;
use App\Domain\Agent\AgentExecution;
use App\Domain\Agent\AgentExecutionStatus;
use App\Domain\Chat\Content\TextContent;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\ConversationState;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use App\Domain\Chat\MessageStatus;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Agent\NullAgentExecutionCanceller;
use App\Tests\Units\Infrastructure\Agent\NullAgentExecutionGateway;
use App\Tests\Units\Infrastructure\Chat\NullConversationGateway;
use App\Tests\Units\Infrastructure\Chat\NullMessageGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

class TimeoutStaleExecutionsHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private NullAgentExecutionGateway $agentExecutionGateway;
    private NullAgentExecutionCanceller $executionCanceller;
    private NullConversationGateway $conversationGateway;
    private NullMessageGateway $messageGateway;
    private RealTimeUpdatePublisherInterface&MockObject $realTimeUpdatePublisher;
    private LocaleSwitcher&Stub $localeSwitcher;
    private TranslatorInterface&Stub $translator;
    private TimeoutStaleExecutionsHandler $handler;

    private function getMessageText(Message $message): string
    {
        foreach ($message->getContents() as $content) {
            if ($content instanceof TextContent) {
                return $content->getContent();
            }
        }

        return '';
    }

    protected function setUp(): void
    {
        $this->agentExecutionGateway = new NullAgentExecutionGateway();
        $this->executionCanceller = new NullAgentExecutionCanceller();
        $this->conversationGateway = new NullConversationGateway();
        $this->messageGateway = new NullMessageGateway();
        $this->realTimeUpdatePublisher = $this->createMock(RealTimeUpdatePublisherInterface::class);
        $this->localeSwitcher = $this->createStub(LocaleSwitcher::class);
        $this->translator = $this->createStub(TranslatorInterface::class);

        $this->localeSwitcher
            ->method('runWithLocale')
            ->willReturnCallback(function (string $locale, callable $callback) {
                return $callback();
            });

        $this->translator
            ->method('trans')
            ->willReturn('The operation timed out. Please try again.');

        $this->handler = new TimeoutStaleExecutionsHandler(
            $this->agentExecutionGateway,
            $this->executionCanceller,
            $this->conversationGateway,
            $this->messageGateway,
            $this->realTimeUpdatePublisher,
            $this->localeSwitcher,
            $this->translator,
            5,
            new NullLogger(),
        );
    }

    public function testTimeoutWithRunningExecutionLinkedToConversation(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');
        $conversation->setState(ConversationState::WaitingForAgent);

        $agentExecution = new AgentExecution('exec-stale', 'ChatSessionMessage');
        $this->forceStartedAt($agentExecution, new \DateTimeImmutable('-10 minutes'));
        $conversation->linkAgentExecution($agentExecution);

        $this->agentExecutionGateway->addExecution($agentExecution);
        $this->conversationGateway->addConversation($conversation);

        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate');
        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishConversationUpdate')
            ->with($conversation);

        // Act
        $result = ($this->handler)(new TimeoutStaleExecutionsAction());

        // Assert
        $this->assertSame(1, $result);

        $this->assertSame(['exec-stale'], $this->executionCanceller->cancelledExecutionIds);
        $this->assertSame(AgentExecutionStatus::TimedOut, $agentExecution->getStatus());
        $this->assertNotNull($agentExecution->getCompletedAt());

        $this->assertSame(ConversationState::Idle, $conversation->getState());

        $messages = $this->messageGateway->getAll();
        $this->assertCount(1, $messages);
        $this->assertSame(MessageRole::SystemError, $messages[0]->getRole());
        $this->assertSame(MessageStatus::Delivered, $messages[0]->getStatus());
        $this->assertSame('The operation timed out. Please try again.', $this->getMessageText($messages[0]));
    }

    public function testTimeoutWithRunningExecutionWithoutConversation(): void
    {
        // Arrange
        $agentExecution = new AgentExecution('exec-orphan', 'DocumentSummary');
        $this->forceStartedAt($agentExecution, new \DateTimeImmutable('-10 minutes'));
        $this->agentExecutionGateway->addExecution($agentExecution);

        $this->realTimeUpdatePublisher
            ->expects($this->never())
            ->method('publishMessageUpdate');
        $this->realTimeUpdatePublisher
            ->expects($this->never())
            ->method('publishConversationUpdate');

        // Act
        $result = ($this->handler)(new TimeoutStaleExecutionsAction());

        // Assert
        $this->assertSame(1, $result);
        $this->assertSame(['exec-orphan'], $this->executionCanceller->cancelledExecutionIds);
        $this->assertSame(AgentExecutionStatus::TimedOut, $agentExecution->getStatus());
        $this->assertEmpty($this->messageGateway->getAll());
    }

    public function testTimeoutWithNoStaleExecutions(): void
    {
        // Arrange — no executions added

        $this->realTimeUpdatePublisher
            ->expects($this->never())
            ->method('publishMessageUpdate');
        $this->realTimeUpdatePublisher
            ->expects($this->never())
            ->method('publishConversationUpdate');

        // Act
        $result = ($this->handler)(new TimeoutStaleExecutionsAction());

        // Assert
        $this->assertSame(0, $result);
        $this->assertEmpty($this->executionCanceller->cancelledExecutionIds);
    }

    public function testTimeoutMultipleStaleExecutions(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');
        $conversation->setState(ConversationState::AgentProcessing);

        $execution1 = new AgentExecution('exec-1', 'ChatSessionMessage');
        $this->forceStartedAt($execution1, new \DateTimeImmutable('-10 minutes'));
        $conversation->linkAgentExecution($execution1);

        $execution2 = new AgentExecution('exec-2', 'DocumentSummary');
        $this->forceStartedAt($execution2, new \DateTimeImmutable('-15 minutes'));

        $this->agentExecutionGateway->addExecution($execution1);
        $this->agentExecutionGateway->addExecution($execution2);
        $this->conversationGateway->addConversation($conversation);

        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate');
        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishConversationUpdate');

        // Act
        $result = ($this->handler)(new TimeoutStaleExecutionsAction());

        // Assert
        $this->assertSame(2, $result);
        $this->assertCount(2, $this->executionCanceller->cancelledExecutionIds);
        $this->assertSame(AgentExecutionStatus::TimedOut, $execution1->getStatus());
        $this->assertSame(AgentExecutionStatus::TimedOut, $execution2->getStatus());
        $this->assertSame(ConversationState::Idle, $conversation->getState());
    }

    /**
     * Force startedAt to a specific time for testing stale detection.
     */
    private function forceStartedAt(AgentExecution $execution, \DateTimeImmutable $startedAt): void
    {
        $reflection = new \ReflectionProperty(AgentExecution::class, 'startedAt');
        $reflection->setValue($execution, $startedAt);
    }
}
