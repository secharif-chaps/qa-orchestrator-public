<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Chat;

use App\Application\Chat\RetryMessageAction;
use App\Application\Chat\RetryMessageHandler;
use App\Domain\AI\ChatSessionInterface;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\ConversationState;
use App\Domain\Chat\MaxRetryAttemptsExceededException;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageCannotBeRetriedException;
use App\Domain\Chat\MessageNotFoundException;
use App\Domain\Chat\MessageStatus;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Chat\NullConversationGateway;
use App\Tests\Units\Infrastructure\Chat\NullMessageGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

#[CoversClass(RetryMessageHandler::class)]
#[CoversClass(RetryMessageAction::class)]
#[CoversClass(MaxRetryAttemptsExceededException::class)]
#[CoversClass(MessageCannotBeRetriedException::class)]
class RetryMessageHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private NullMessageGateway $messageGateway;
    private NullConversationGateway $conversationGateway;
    private ChatSessionInterface&MockObject $chatSession;
    private RealTimeUpdatePublisherInterface&MockObject $realTimeUpdatePublisher;
    private Security&Stub $security;
    private RetryMessageHandler $handler;

    protected function setUp(): void
    {
        $this->messageGateway = new NullMessageGateway();
        $this->conversationGateway = new NullConversationGateway();
        $this->chatSession = $this->createMock(ChatSessionInterface::class);
        $this->realTimeUpdatePublisher = $this->createMock(RealTimeUpdatePublisherInterface::class);
        $this->security = $this->createStub(Security::class);

        $this->handler = new RetryMessageHandler(
            $this->messageGateway,
            $this->conversationGateway,
            $this->chatSession,
            $this->realTimeUpdatePublisher,
            $this->security,
        );
    }

    public function testSuccessfulRetryIncrementsRetryCountAndChangesStatus(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');
        $this->conversationGateway->addConversation($conversation);

        $message = new Message($conversation);
        $messageId = Uuid::v4();
        $this->forcePropertyValue($message, $messageId->toRfc4122());
        $message->setStatus(MessageStatus::Error);
        $message->setRetryCount(0);
        $this->messageGateway->addMessage($message);

        $action = new RetryMessageAction($messageId);

        $this->chatSession
            ->expects($this->once())
            ->method('sendMessage')
            ->with($conversation, $message)
            ->willReturn($message);

        $this->realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate')
            ->with($message);

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertSame($message, $result);
        $this->assertSame(MessageStatus::Pending, $message->getStatus());
        $this->assertSame(1, $message->getRetryCount());
        $this->assertSame(ConversationState::WaitingForAgent, $conversation->getState());
    }

    public function testRetryRespectsMaximumLimit(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');
        $this->conversationGateway->addConversation($conversation);

        $message = new Message($conversation);
        $messageId = Uuid::v4();
        $this->forcePropertyValue($message, $messageId->toRfc4122());
        $message->setStatus(MessageStatus::Error);
        $message->setRetryCount(3); // Already at max
        $this->messageGateway->addMessage($message);

        $action = new RetryMessageAction($messageId);

        $this->chatSession
            ->expects($this->never())
            ->method('sendMessage');

        $this->realTimeUpdatePublisher
            ->expects($this->never())
            ->method('publishMessageUpdate');

        // Act & Assert
        $this->expectException(MaxRetryAttemptsExceededException::class);
        $this->expectExceptionMessage('has reached the maximum retry limit of 3 attempts');

        ($this->handler)($action);
    }

    public function testRetryFailsGracefullyWhenMessageNotFound(): void
    {
        // Arrange
        $nonExistentMessageId = Uuid::v4();
        $action = new RetryMessageAction($nonExistentMessageId);

        $this->chatSession
            ->expects($this->never())
            ->method('sendMessage');

        $this->realTimeUpdatePublisher
            ->expects($this->never())
            ->method('publishMessageUpdate');

        // Act & Assert
        $this->expectException(MessageNotFoundException::class);
        $this->expectExceptionMessage(\sprintf('Message with ID "%s" not found.', $nonExistentMessageId->toRfc4122()));

        ($this->handler)($action);
    }

    public function testRetryFailsWhenMessageNotInErrorStatus(): void
    {
        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');
        $this->conversationGateway->addConversation($conversation);

        $message = new Message($conversation);
        $messageId = Uuid::v4();
        $this->forcePropertyValue($message, $messageId->toRfc4122());
        $message->setStatus(MessageStatus::Sent); // Not in error status
        $this->messageGateway->addMessage($message);

        $action = new RetryMessageAction($messageId);

        $this->chatSession
            ->expects($this->never())
            ->method('sendMessage');

        $this->realTimeUpdatePublisher
            ->expects($this->never())
            ->method('publishMessageUpdate');

        // Act & Assert
        $this->expectException(MessageCannotBeRetriedException::class);
        $this->expectExceptionMessage('cannot be retried because it is not in error status');

        ($this->handler)($action);
    }
}
