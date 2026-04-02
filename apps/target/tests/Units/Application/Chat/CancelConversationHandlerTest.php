<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Chat;

use App\Application\Chat\CancelConversationAction;
use App\Application\Chat\CancelConversationHandler;
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

class CancelConversationHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private NullConversationGateway $conversationGateway;
    private NullAgentExecutionGateway $agentExecutionGateway;
    private NullAgentExecutionCanceller $executionCanceller;
    private NullMessageGateway $messageGateway;
    private RealTimeUpdatePublisherInterface&MockObject $realTimeUpdatePublisher;
    private LocaleSwitcher&Stub $localeSwitcher;
    private TranslatorInterface&Stub $translator;
    private CancelConversationHandler $handler;

    protected function setUp(): void
    {
        $this->conversationGateway = new NullConversationGateway();
        $this->agentExecutionGateway = new NullAgentExecutionGateway();
        $this->executionCanceller = new NullAgentExecutionCanceller();
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
            ->willReturn('The operation has been cancelled.');

        $this->handler = new CancelConversationHandler(
            $this->conversationGateway,
            $this->agentExecutionGateway,
            $this->executionCanceller,
            $this->messageGateway,
            $this->realTimeUpdatePublisher,
            $this->localeSwitcher,
            $this->translator,
            new NullLogger(),
        );
    }

    private function getMessageText(Message $message): string
    {
        foreach ($message->getContents() as $content) {
            if ($content instanceof TextContent) {
                return $content->getContent();
            }
        }

        return '';
    }

    public function testCancelWithRunningAgentExecution(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');
        $conversation->setState(ConversationState::WaitingForAgent);

        $agentExecution = new AgentExecution('exec-123', 'ChatSessionMessage');
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
        $action = new CancelConversationAction(conversationId: 'conversation-id');
        $result = ($this->handler)($action);

        // Assert
        $this->assertSame($conversation, $result);
        $this->assertSame(ConversationState::Idle, $result->getState());

        $this->assertSame(['exec-123'], $this->executionCanceller->cancelledExecutionIds);
        $this->assertSame(AgentExecutionStatus::Cancelled, $agentExecution->getStatus());
        $this->assertNotNull($agentExecution->getCompletedAt());

        $this->assertSame($agentExecution, $this->agentExecutionGateway->savedExecution);

        $messages = $this->messageGateway->getAll();
        $this->assertCount(1, $messages);
        $this->assertSame(MessageRole::SystemError, $messages[0]->getRole());
        $this->assertSame(MessageStatus::Delivered, $messages[0]->getStatus());
        $this->assertSame('The operation has been cancelled.', $this->getMessageText($messages[0]));
    }

    public function testCancelWithoutAgentExecution(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');
        $conversation->setState(ConversationState::WaitingForAgent);
        $this->conversationGateway->addConversation($conversation);

        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate');
        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishConversationUpdate')
            ->with($conversation);

        // Act
        $action = new CancelConversationAction(conversationId: 'conversation-id');
        $result = ($this->handler)($action);

        // Assert
        $this->assertSame(ConversationState::Idle, $result->getState());
        $this->assertEmpty($this->executionCanceller->cancelledExecutionIds);
        $this->assertNull($this->agentExecutionGateway->savedExecution);

        $messages = $this->messageGateway->getAll();
        $this->assertCount(1, $messages);
        $this->assertSame(MessageRole::SystemError, $messages[0]->getRole());
    }

    public function testCancelWithAlreadyTerminalExecution(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');
        $conversation->setState(ConversationState::AgentProcessing);

        $agentExecution = new AgentExecution('exec-456', 'ChatSessionMessage');
        $agentExecution->fail();
        $conversation->linkAgentExecution($agentExecution);
        $this->agentExecutionGateway->addExecution($agentExecution);
        $this->conversationGateway->addConversation($conversation);

        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate');
        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishConversationUpdate');

        // Act
        $action = new CancelConversationAction(conversationId: 'conversation-id');
        $result = ($this->handler)($action);

        // Assert — execution not cancelled again, no save on gateway
        $this->assertSame(ConversationState::Idle, $result->getState());
        $this->assertEmpty($this->executionCanceller->cancelledExecutionIds);
        $this->assertSame(AgentExecutionStatus::Failed, $agentExecution->getStatus());

        $messages = $this->messageGateway->getAll();
        $this->assertCount(1, $messages);
        $this->assertSame(MessageRole::SystemError, $messages[0]->getRole());
    }
}
