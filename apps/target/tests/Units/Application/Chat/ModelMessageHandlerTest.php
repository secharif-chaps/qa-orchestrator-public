<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Chat;

use App\Application\Chat\ModelMessageAction;
use App\Application\Chat\ModelMessageHandler;
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
use App\Domain\WatchFile\WatchFileStatus;
use App\Tests\Units\Infrastructure\Agent\NullAgentExecutionGateway;
use App\Tests\Units\Infrastructure\AI\NullLlmOutputSanitizer;
use App\Tests\Units\Infrastructure\Chat\NullConversationGateway;
use App\Tests\Units\Infrastructure\Chat\NullMessageGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\MockHelpersTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ModelMessageHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    use MockHelpersTrait;
    private NullConversationGateway $conversationGateway;
    private NullAgentExecutionGateway $agentExecutionGateway;
    private NullMessageGateway $messageGateway;
    private RealTimeUpdatePublisherInterface&Stub $realTimeUpdatePublisher;
    private LoggerInterface&Stub $logger;
    private ModelMessageHandler $handler;

    private function getMessageText(Message $message): string
    {
        foreach ($message->getContents() as $content) {
            if ($content instanceof TextContent) {
                return $content->getContent();
            }
        }

        return '';
    }

    private function createConversation(
        WatchFile $watchFile,
        string $id,
        ConversationState $state = ConversationState::WaitingForAgent,
    ): Conversation {
        $conversation = new Conversation($watchFile);
        $conversation->setState($state);
        $this->forcePropertyValue($conversation, $id);
        $this->conversationGateway->addConversation($conversation);

        return $conversation;
    }

    protected function setUp(): void
    {
        $this->conversationGateway = new NullConversationGateway();
        $this->agentExecutionGateway = new NullAgentExecutionGateway();
        $this->messageGateway = new NullMessageGateway();
        $this->realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->buildHandler();
    }

    private function buildHandler(): void
    {
        $this->handler = new ModelMessageHandler(
            $this->conversationGateway,
            $this->agentExecutionGateway,
            $this->messageGateway,
            $this->realTimeUpdatePublisher,
            new NullLlmOutputSanitizer(),
            $this->logger,
        );
    }

    public function testHandleModelMessageSuccessfully(): void
    {
        $realTimeUpdatePublisher = $this->createMockWithExpectations(RealTimeUpdatePublisherInterface::class);
        $logger = $this->createMockWithExpectations(LoggerInterface::class);
        $this->realTimeUpdatePublisher = $realTimeUpdatePublisher;
        $this->logger = $logger;
        $this->buildHandler();

        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::DRAFT);

        $conversation = $this->createConversation($watchFile, 'conversation-id');

        $action = new ModelMessageAction(conversationId: 'conversation-id', message: 'This is a model response');

        $realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate')
            ->with($this->callback(function (Message $message) {
                return MessageRole::Model === $message->getRole()
                    && 'This is a model response' === $this->getMessageText($message);
            }));

        $logger
            ->expects($this->once())
            ->method('info')
            ->with('Model message added to conversation', $this->callback(function (array $context) {
                return 'conversation-id' === $context['conversation_id']
                    && isset($context['message_id'])
                    && \is_string($context['message_id']);
            }));

        // Act
        ($this->handler)($action);

        // Assert - conversation is saved to update state to idle
        $this->assertSame($conversation, $this->conversationGateway->savedConversation);
        $this->assertSame(ConversationState::Idle, $conversation->getState());

        // Verify message was saved through the message gateway
        $this->assertCount(1, $this->messageGateway->getAll());
        $savedMessage = $this->messageGateway->getAll()[0];
        $this->assertInstanceOf(Message::class, $savedMessage);
        $this->assertEquals(MessageRole::Model, $savedMessage->getRole());
        $this->assertEquals('This is a model response', $this->getMessageText($savedMessage));
        $this->assertSame($conversation, $savedMessage->getConversation());
        $this->assertNotNull($savedMessage->getId());
        $this->assertTrue($this->messageGateway->hasMessage($savedMessage->getId()));
        $this->assertSame(MessageStatus::Delivered, $savedMessage->getStatus());
    }

    public function testHandleModelMessageWithEmptyMessage(): void
    {
        $realTimeUpdatePublisher = $this->createMockWithExpectations(RealTimeUpdatePublisherInterface::class);
        $logger = $this->createMockWithExpectations(LoggerInterface::class);
        $this->realTimeUpdatePublisher = $realTimeUpdatePublisher;
        $this->logger = $logger;
        $this->buildHandler();

        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::DRAFT);

        $conversation = $this->createConversation($watchFile, 'conversation-id');

        $action = new ModelMessageAction(conversationId: 'conversation-id', message: '');

        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('Unable to retrieve message from model response');

        $realTimeUpdatePublisher
            ->expects($this->never())
            ->method('publishMessageUpdate');

        // Act
        ($this->handler)($action);

        // Assert - conversation NOT saved when message is empty
        $this->assertNull($this->conversationGateway->savedConversation);

        // Verify no message was saved through the message gateway since message was empty
        $this->assertCount(0, $this->messageGateway->getAll());
    }

    public function testHandleModelMessageWithoutLogger(): void
    {
        $realTimeUpdatePublisher = $this->createMockWithExpectations(RealTimeUpdatePublisherInterface::class);
        $this->realTimeUpdatePublisher = $realTimeUpdatePublisher;

        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::DRAFT);

        $conversation = $this->createConversation($watchFile, 'conversation-id');

        $handlerWithoutLogger = new ModelMessageHandler(
            $this->conversationGateway,
            $this->agentExecutionGateway,
            $this->messageGateway,
            $this->realTimeUpdatePublisher,
            new NullLlmOutputSanitizer(),
        );

        $action = new ModelMessageAction(conversationId: 'conversation-id', message: 'This is a model response');

        $realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate')
            ->with($this->isInstanceOf(Message::class));

        // Act
        ($handlerWithoutLogger)($action);

        // Assert - conversation is saved to update state to idle
        $this->assertSame($conversation, $this->conversationGateway->savedConversation);
        $this->assertSame(ConversationState::Idle, $conversation->getState());

        // Verify message was saved through the message gateway
        $this->assertCount(1, $this->messageGateway->getAll());
        $savedMessage = $this->messageGateway->getAll()[0];
        $this->assertInstanceOf(Message::class, $savedMessage);
        $this->assertEquals(MessageRole::Model, $savedMessage->getRole());
        $this->assertEquals('This is a model response', $this->getMessageText($savedMessage));
        $this->assertNotNull($savedMessage->getId());
        $this->assertTrue($this->messageGateway->hasMessage($savedMessage->getId()));
        $this->assertSame(MessageStatus::Delivered, $savedMessage->getStatus());
    }

    public function testHandleModelMessageWithComplexMessage(): void
    {
        $realTimeUpdatePublisher = $this->createMockWithExpectations(RealTimeUpdatePublisherInterface::class);
        $logger = $this->createMockWithExpectations(LoggerInterface::class);
        $this->realTimeUpdatePublisher = $realTimeUpdatePublisher;
        $this->logger = $logger;
        $this->buildHandler();

        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::DRAFT);

        $conversation = $this->createConversation($watchFile, 'conversation-id');

        $complexMessage = "This is a complex model response with multiple lines.\nIt contains special characters: éàçù and numbers: 123.\nIt also has punctuation marks: !@#$%^&*()";

        $action = new ModelMessageAction(conversationId: 'conversation-id', message: $complexMessage);

        $realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate')
            ->with($this->callback(function (Message $message) use ($complexMessage) {
                return MessageRole::Model === $message->getRole()
                    && $this->getMessageText($message) === $complexMessage;
            }));

        $logger
            ->expects($this->once())
            ->method('info')
            ->with('Model message added to conversation', $this->callback(function (array $context) {
                return 'conversation-id' === $context['conversation_id']
                    && isset($context['message_id'])
                    && \is_string($context['message_id']);
            }));

        // Act
        ($this->handler)($action);

        // Assert - conversation is saved to update state to idle
        $this->assertSame($conversation, $this->conversationGateway->savedConversation);
        $this->assertSame(ConversationState::Idle, $conversation->getState());

        // Verify message was saved through the message gateway
        $this->assertCount(1, $this->messageGateway->getAll());
        $savedMessage = $this->messageGateway->getAll()[0];
        $this->assertInstanceOf(Message::class, $savedMessage);
        $this->assertEquals(MessageRole::Model, $savedMessage->getRole());
        $this->assertEquals($complexMessage, $this->getMessageText($savedMessage));
        $this->assertNotNull($savedMessage->getId());
        $this->assertTrue($this->messageGateway->hasMessage($savedMessage->getId()));
        $this->assertSame(MessageStatus::Delivered, $savedMessage->getStatus());
    }

    public function testHandleModelMessageSucceedsWhenWatchFileIsEnabled(): void
    {
        $realTimeUpdatePublisher = $this->createMockWithExpectations(RealTimeUpdatePublisherInterface::class);
        $logger = $this->createMockWithExpectations(LoggerInterface::class);
        $this->realTimeUpdatePublisher = $realTimeUpdatePublisher;
        $this->logger = $logger;
        $this->buildHandler();

        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::ENABLED);

        $conversation = $this->createConversation($watchFile, 'conversation-id');

        $action = new ModelMessageAction(conversationId: 'conversation-id', message: 'This is a model response');

        $realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate')
            ->with($this->callback(function (Message $message) {
                return MessageRole::Model === $message->getRole()
                    && 'This is a model response' === $this->getMessageText($message);
            }));

        $logger
            ->expects($this->once())
            ->method('info');

        // Act
        ($this->handler)($action);

        // Assert
        $this->assertCount(1, $this->messageGateway->getAll());
        $savedMessage = $this->messageGateway->getAll()[0];
        $this->assertEquals(MessageRole::Model, $savedMessage->getRole());
    }

    public function testModelMessageCompletesAgentExecution(): void
    {
        $publisher = $this->createMockWithExpectations(RealTimeUpdatePublisherInterface::class);
        $this->realTimeUpdatePublisher = $publisher;
        $this->buildHandler();

        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::DRAFT);

        $conversation = $this->createConversation($watchFile, 'conversation-id');

        $agentExecution = new AgentExecution('exec-123', 'chat_message');
        $this->agentExecutionGateway->addExecution($agentExecution);
        $conversation->linkAgentExecution($agentExecution);

        $action = new ModelMessageAction(conversationId: 'conversation-id', message: 'Agent response');

        $publisher->expects($this->once())
->method('publishMessageUpdate');
        $publisher->expects($this->once())
->method('publishConversationUpdate');

        // Act
        ($this->handler)($action);

        // Assert
        $this->assertSame(AgentExecutionStatus::Completed, $agentExecution->getStatus());
        $this->assertNotNull($agentExecution->getCompletedAt());
        $this->assertSame($agentExecution, $this->agentExecutionGateway->savedExecution);
        $this->assertNull($conversation->getAgentExecution());
    }

    public function testModelMessageWithoutAgentExecution(): void
    {
        $publisher = $this->createMockWithExpectations(RealTimeUpdatePublisherInterface::class);
        $this->realTimeUpdatePublisher = $publisher;
        $this->buildHandler();

        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::DRAFT);

        $conversation = $this->createConversation($watchFile, 'conversation-id');

        $action = new ModelMessageAction(conversationId: 'conversation-id', message: 'Response without agent');

        $publisher->expects($this->once())
->method('publishMessageUpdate');
        $publisher->expects($this->once())
->method('publishConversationUpdate');

        // Act
        ($this->handler)($action);

        // Assert — no error, execution gateway not called
        $this->assertNull($this->agentExecutionGateway->savedExecution);
        $this->assertNull($conversation->getAgentExecution());
        $this->assertSame(ConversationState::Idle, $conversation->getState());
    }

    public function testModelMessageDiscardsWhenConversationIsIdle(): void
    {
        $publisher = $this->createMockWithExpectations(RealTimeUpdatePublisherInterface::class);
        $this->realTimeUpdatePublisher = $publisher;
        $logger = $this->createMockWithExpectations(LoggerInterface::class);
        $this->logger = $logger;
        $this->buildHandler();

        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::DRAFT);

        // Conversation in Idle state (e.g. after cancellation)
        $conversation = $this->createConversation($watchFile, 'conversation-id', ConversationState::Idle);

        $action = new ModelMessageAction(conversationId: 'conversation-id', message: 'Late response after cancel');

        $publisher
            ->expects($this->never())
            ->method('publishMessageUpdate');

        $logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Discarding model message for idle conversation (likely cancelled)',
                $this->callback(function (array $context) {
                    return 'conversation-id' === $context['conversation_id'];
                })
            );

        // Act
        ($this->handler)($action);

        // Assert — nothing saved
        $this->assertNull($this->conversationGateway->savedConversation);
        $this->assertCount(0, $this->messageGateway->getAll());
    }

    public function testModelMessageDiscardsWhenExecutionIsTerminal(): void
    {
        $publisher = $this->createMockWithExpectations(RealTimeUpdatePublisherInterface::class);
        $this->realTimeUpdatePublisher = $publisher;
        $this->buildHandler();

        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::DRAFT);

        $conversation = $this->createConversation($watchFile, 'conversation-id');

        $agentExecution = new AgentExecution('exec-456', 'chat_message');
        $agentExecution->complete(); // Already terminal
        $this->agentExecutionGateway->addExecution($agentExecution);
        $conversation->linkAgentExecution($agentExecution);

        $action = new ModelMessageAction(conversationId: 'conversation-id', message: 'Late response');

        $publisher->expects($this->never())
->method('publishMessageUpdate');
        $publisher->expects($this->never())
->method('publishConversationUpdate');

        // Act
        ($this->handler)($action);

        // Assert — message discarded, nothing saved
        $this->assertNull($this->conversationGateway->savedConversation);
        $this->assertCount(0, $this->messageGateway->getAll());
    }
}
