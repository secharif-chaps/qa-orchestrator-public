<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Chat;

use App\Application\Chat\ErrorModelMessageAction;
use App\Application\Chat\ErrorModelMessageHandler;
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
use App\Tests\Units\Infrastructure\Agent\NullAgentExecutionGateway;
use App\Tests\Units\Infrastructure\Chat\NullConversationGateway;
use App\Tests\Units\Infrastructure\Chat\NullMessageGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

class ErrorModelMessageHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private NullConversationGateway $conversationGateway;
    private NullAgentExecutionGateway $agentExecutionGateway;
    private NullMessageGateway $messageGateway;
    private RealTimeUpdatePublisherInterface&MockObject $realTimeUpdatePublisher;
    private LocaleSwitcher&Stub $localeSwitcher;
    private TranslatorInterface&Stub $translator;
    private ErrorModelMessageHandler $handler;

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
        $this->realTimeUpdatePublisher = $this->createMock(RealTimeUpdatePublisherInterface::class);
        $this->localeSwitcher = $this->createStub(LocaleSwitcher::class);
        $this->translator = $this->createStub(TranslatorInterface::class);

        // Configure LocaleSwitcher to execute the callback immediately
        $this->localeSwitcher
            ->method('runWithLocale')
            ->willReturnCallback(function (string $locale, callable $callback) {
                return $callback();
            });

        $this->handler = new ErrorModelMessageHandler(
            $this->realTimeUpdatePublisher,
            $this->conversationGateway,
            $this->agentExecutionGateway,
            $this->messageGateway,
            $this->localeSwitcher,
            $this->translator,
            300,
        );
    }

    public function testHandleErrorModelMessageWithConversationId(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $conversation = $this->createConversation($watchFile, 'conversation-id');

        $errorTime = new \DateTimeImmutable('2024-01-15 10:30:00');
        $action = new ErrorModelMessageAction(
            error: 'Model timeout error',
            messageId: 'message-123',
            conversationId: 'conversation-id',
            watchFileId: 'watchfile-id',
            errorTime: $errorTime,
        );

        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate')
            ->with($this->callback(function (Message $message) {
                return MessageRole::SystemError === $message->getRole();
            }));

        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishConversationUpdate')
            ->with($this->callback(function (Conversation $conv) use ($conversation) {
                return $conv === $conversation;
            }));

        // Act
        ($this->handler)($action);

        // Assert
        $savedConversation = $this->conversationGateway->savedConversation;
        $this->assertNotNull($savedConversation);
        $this->assertCount(1, $savedConversation->getMessages());
        $this->assertSame(ConversationState::Idle, $savedConversation->getState());

        $message = $savedConversation->getMessages()[0];
        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals(MessageRole::SystemError, $message->getRole());
        $this->assertSame($conversation, $message->getConversation());
        $this->assertSame(MessageStatus::Delivered, $message->getStatus());

        // When action->error is not empty, it's used as message text
        $this->assertSame('Model timeout error', $this->getMessageText($message));

        $messageMetadata = $message->getMetadata();
        $this->assertNotNull($messageMetadata);

        $this->assertArrayHasKey('error', $messageMetadata);
        $this->assertEquals('Model timeout error', $messageMetadata['error']);

        $this->assertArrayHasKey('messageId', $messageMetadata);
        $this->assertEquals('message-123', $messageMetadata['messageId']);

        $this->assertArrayHasKey('context', $messageMetadata);
        $this->assertEquals([], $messageMetadata['context']);
    }

    public function testHandleErrorModelMessageWithoutConversationId(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $conversation = $this->createConversation($watchFile, 'conversation-id');

        $errorTime = new \DateTimeImmutable('2024-01-15 10:30:00');
        $action = new ErrorModelMessageAction(
            error: 'Model connection error',
            messageId: null,
            conversationId: null,
            watchFileId: 'watchfile-id',
            errorTime: $errorTime,
        );

        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate')
            ->with($this->callback(function (Message $message) {
                return MessageRole::SystemError === $message->getRole();
            }));

        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishConversationUpdate');

        // Act
        ($this->handler)($action);

        // Assert
        $savedConversation = $this->conversationGateway->savedConversation;
        $this->assertNotNull($savedConversation);
        $this->assertCount(1, $savedConversation->getMessages());
        $this->assertSame(ConversationState::Idle, $savedConversation->getState());

        $message = $savedConversation->getMessages()[0];
        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals(MessageRole::SystemError, $message->getRole());
        $this->assertSame($conversation, $message->getConversation());
        $this->assertSame(MessageStatus::Delivered, $message->getStatus());

        // When action->error is not empty, it's used as message text
        $this->assertSame('Model connection error', $this->getMessageText($message));

        $messageMetadata = $message->getMetadata();
        $this->assertNotNull($messageMetadata);

        $this->assertArrayHasKey('error', $messageMetadata);
        $this->assertEquals('Model connection error', $messageMetadata['error']);

        $this->assertArrayHasKey('messageId', $messageMetadata);
        $this->assertNull($messageMetadata['messageId']);

        $this->assertArrayHasKey('context', $messageMetadata);
        $this->assertEquals([], $messageMetadata['context']);
    }

    public function testHandleErrorModelMessageWithComplexErrorData(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $this->createConversation($watchFile, 'conversation-id');

        $complexError = '{"type": "timeout", "code": 408, "details": "Request timed out after 30 seconds"}';
        $errorTime = new \DateTimeImmutable('2024-01-15 10:30:00');
        $action = new ErrorModelMessageAction(
            error: $complexError,
            messageId: 'complex-message-456',
            conversationId: 'conversation-id',
            watchFileId: 'watchfile-id',
            context: [
                'retry_count' => 3,
                'model' => 'gpt-4',
            ],
            errorTime: $errorTime,
        );

        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate')
            ->with($this->callback(function (Message $message) {
                return MessageRole::SystemError === $message->getRole();
            }));

        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishConversationUpdate');

        // Act
        ($this->handler)($action);

        // Assert
        $savedConversation = $this->conversationGateway->savedConversation;
        $this->assertNotNull($savedConversation);
        $this->assertCount(1, $savedConversation->getMessages());
        $this->assertSame(ConversationState::Idle, $savedConversation->getState());

        $message = $savedConversation->getMessages()[0];
        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals(MessageRole::SystemError, $message->getRole());
        $this->assertSame(MessageStatus::Delivered, $message->getStatus());

        // When action->error is not empty, it's used as message text
        $this->assertSame($complexError, $this->getMessageText($message));

        $messageMetadata = $message->getMetadata();
        $this->assertNotNull($messageMetadata);

        $this->assertArrayHasKey('error', $messageMetadata);
        $this->assertEquals($complexError, $messageMetadata['error']);

        $this->assertArrayHasKey('messageId', $messageMetadata);
        $this->assertEquals('complex-message-456', $messageMetadata['messageId']);

        // Verify context is stored in metadata
        $this->assertArrayHasKey('context', $messageMetadata);
        $this->assertEquals([
            'retry_count' => 3,
            'model' => 'gpt-4',
        ], $messageMetadata['context']);
    }

    public function testHandleErrorModelMessageUpdatesOriginalMessageStatus(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $conversation = $this->createConversation($watchFile, 'conversation-id');

        // Create original message in sent status
        $originalMessage = new Message($conversation);
        $originalMessage->setTextContent('Original user message');
        $originalMessage->setStatus(MessageStatus::Sent);
        $this->messageGateway->addMessage($originalMessage);

        $errorTime = new \DateTimeImmutable('2024-01-15 10:30:00');
        $action = new ErrorModelMessageAction(
            error: 'Model timeout error',
            messageId: $originalMessage->getId(),
            conversationId: 'conversation-id',
            watchFileId: 'watchfile-id',
            errorTime: $errorTime,
        );

        // Expect 2 calls: one for original message (status update) and one for error message
        $this->realTimeUpdatePublisher
            ->expects($this->exactly(2))
            ->method('publishMessageUpdate');

        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishConversationUpdate');

        // Act
        ($this->handler)($action);

        // Assert - original message status updated to error
        $this->assertSame(MessageStatus::Error, $originalMessage->getStatus());

        // Verify conversation state is idle
        $savedConversation = $this->conversationGateway->savedConversation;
        $this->assertNotNull($savedConversation);
        $this->assertSame(ConversationState::Idle, $savedConversation->getState());
    }

    public function testHandleErrorModelMessageUsesTranslatedMessageWhenErrorIsEmpty(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $this->createConversation($watchFile, 'conversation-id');

        $errorTime = new \DateTimeImmutable('2024-01-15 10:30:00');
        $action = new ErrorModelMessageAction(
            error: '', // Empty error should use translated message
            messageId: null,
            conversationId: 'conversation-id',
            watchFileId: 'watchfile-id',
            errorTime: $errorTime,
        );

        $translatedMessage = 'We\'re sorry, but we\'re unable to process your request at this time.';
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->with('model.error_message')
            ->willReturn($translatedMessage);

        $handler = new ErrorModelMessageHandler(
            $this->realTimeUpdatePublisher,
            $this->conversationGateway,
            $this->agentExecutionGateway,
            $this->messageGateway,
            $this->localeSwitcher,
            $translator,
            300,
        );

        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate');

        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishConversationUpdate');

        // Act
        $handler($action);

        // Assert
        $savedConversation = $this->conversationGateway->savedConversation;
        $this->assertNotNull($savedConversation);

        $message = $savedConversation->getMessages()[0];
        $this->assertInstanceOf(Message::class, $message);

        // When action->error is empty, translated message is used
        $this->assertSame($translatedMessage, $this->getMessageText($message));
    }

    public function testErrorMessageFailsAgentExecution(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $conversation = $this->createConversation($watchFile, 'conversation-id');

        $agentExecution = new AgentExecution('exec-123', 'chat_message');
        $this->agentExecutionGateway->addExecution($agentExecution);
        $conversation->linkAgentExecution($agentExecution);

        $errorTime = new \DateTimeImmutable('2024-01-15 10:30:00');
        $action = new ErrorModelMessageAction(
            error: 'Model timeout error',
            messageId: null,
            conversationId: 'conversation-id',
            watchFileId: 'watchfile-id',
            errorTime: $errorTime,
        );

        $this->realTimeUpdatePublisher->expects($this->once())
            ->method('publishMessageUpdate');
        $this->realTimeUpdatePublisher->expects($this->once())
            ->method('publishConversationUpdate');

        // Act
        ($this->handler)($action);

        // Assert
        $this->assertSame(AgentExecutionStatus::Failed, $agentExecution->getStatus());
        $this->assertNotNull($agentExecution->getCompletedAt());
        $this->assertSame($agentExecution, $this->agentExecutionGateway->savedExecution);
        $this->assertNull($conversation->getAgentExecution());
    }

    public function testErrorMessageWithoutAgentExecution(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $conversation = $this->createConversation($watchFile, 'conversation-id');

        $errorTime = new \DateTimeImmutable('2024-01-15 10:30:00');
        $action = new ErrorModelMessageAction(
            error: 'Some error',
            messageId: null,
            conversationId: 'conversation-id',
            watchFileId: 'watchfile-id',
            errorTime: $errorTime,
        );

        $this->realTimeUpdatePublisher->expects($this->once())
            ->method('publishMessageUpdate');
        $this->realTimeUpdatePublisher->expects($this->once())
            ->method('publishConversationUpdate');

        // Act
        ($this->handler)($action);

        // Assert — no error, execution gateway not called
        $this->assertNull($this->agentExecutionGateway->savedExecution);
        $this->assertNull($conversation->getAgentExecution());
        $this->assertSame(ConversationState::Idle, $conversation->getState());
    }

    public function testErrorMessageDiscardsWhenConversationIsIdle(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        // Conversation in Idle state (e.g. after cancellation)
        $conversation = $this->createConversation($watchFile, 'conversation-id', ConversationState::Idle);

        $errorTime = new \DateTimeImmutable('2024-01-15 10:30:00');
        $action = new ErrorModelMessageAction(
            error: 'Late error after cancel',
            messageId: null,
            conversationId: 'conversation-id',
            watchFileId: 'watchfile-id',
            errorTime: $errorTime,
        );

        $this->realTimeUpdatePublisher
            ->expects($this->never())
            ->method('publishMessageUpdate');

        $this->realTimeUpdatePublisher
            ->expects($this->never())
            ->method('publishConversationUpdate');

        // Act
        ($this->handler)($action);

        // Assert — nothing saved
        $this->assertNull($this->conversationGateway->savedConversation);
        $this->assertCount(0, $conversation->getMessages());
    }

    public function testErrorMessageDiscardsWhenExecutionIsTerminal(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $conversation = $this->createConversation($watchFile, 'conversation-id');

        $agentExecution = new AgentExecution('exec-456', 'chat_message');
        $agentExecution->fail(); // Already terminal
        $this->agentExecutionGateway->addExecution($agentExecution);
        $conversation->linkAgentExecution($agentExecution);

        $errorTime = new \DateTimeImmutable('2024-01-15 10:30:00');
        $action = new ErrorModelMessageAction(
            error: 'Late error',
            messageId: null,
            conversationId: 'conversation-id',
            watchFileId: 'watchfile-id',
            errorTime: $errorTime,
        );

        $this->realTimeUpdatePublisher->expects($this->never())
            ->method('publishMessageUpdate');
        $this->realTimeUpdatePublisher->expects($this->never())
            ->method('publishConversationUpdate');

        // Act
        ($this->handler)($action);

        // Assert — message discarded, nothing saved
        $this->assertNull($this->conversationGateway->savedConversation);
        $this->assertCount(0, $this->messageGateway->getAll());
    }
}
