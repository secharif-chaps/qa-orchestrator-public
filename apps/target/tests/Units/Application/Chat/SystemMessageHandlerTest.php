<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Chat;

use App\Application\Chat\SystemMessageAction;
use App\Application\Chat\SystemMessageHandler;
use App\Domain\Chat\Content\TextContent;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Tests\Units\Infrastructure\AI\NullLlmOutputSanitizer;
use App\Tests\Units\Infrastructure\Chat\NullConversationGateway;
use App\Tests\Units\Infrastructure\Chat\NullMessageGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\MockHelpersTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(SystemMessageHandler::class)]
class SystemMessageHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    use MockHelpersTrait;
    private NullConversationGateway $conversationGateway;
    private NullMessageGateway $messageGateway;
    private CacheItemPoolInterface&Stub $cache;
    private RealTimeUpdatePublisherInterface&Stub $realTimeUpdatePublisher;
    private LoggerInterface&Stub $logger;
    private SystemMessageHandler $handler;

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
        $this->conversationGateway = new NullConversationGateway();
        $this->messageGateway = new NullMessageGateway();
        $this->cache = $this->createStub(CacheItemPoolInterface::class);
        $this->realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        // Configure cache mock to return non-hit items by default (lock can be acquired)
        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem->method('isHit')
            ->willReturn(false);
        $cacheItem->method('set')
            ->willReturnSelf();
        $cacheItem->method('expiresAfter')
            ->willReturnSelf();
        $this->cache->method('getItem')
            ->willReturn($cacheItem);
        $this->cache->method('save')
            ->willReturn(true);

        $this->buildHandler();
    }

    private function buildHandler(): void
    {
        $this->handler = new SystemMessageHandler(
            $this->conversationGateway,
            $this->messageGateway,
            $this->cache,
            $this->realTimeUpdatePublisher,
            new NullLlmOutputSanitizer(),
            $this->logger
        );
    }

    public function testHandleSystemMessageSuccessfully(): void
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

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');
        $this->conversationGateway->addConversation($conversation);

        $action = new SystemMessageAction(conversationId: 'conversation-id', message: 'This is a system message');

        $realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate')
            ->with($this->callback(function (Message $message) {
                return MessageRole::System === $message->getRole()
                    && 'This is a system message' === $this->getMessageText($message);
            }));

        $logger
            ->expects($this->once())
            ->method('info')
            ->with('System message added to conversation', $this->callback(function (array $context) {
                return 'conversation-id' === $context['conversation_id']
                    && isset($context['message_id'])
                    && \is_string($context['message_id']);
            }));

        // Act
        ($this->handler)($action);

        // Assert
        $this->assertNull($this->conversationGateway->savedConversation);

        // Verify message was saved through the message gateway
        $this->assertCount(1, $this->messageGateway->getAll());
        $savedMessage = $this->messageGateway->getAll()[0];
        $this->assertInstanceOf(Message::class, $savedMessage);
        $this->assertEquals(MessageRole::System, $savedMessage->getRole());
        $this->assertEquals('This is a system message', $this->getMessageText($savedMessage));
        $this->assertSame($conversation, $savedMessage->getConversation());
        $this->assertNotNull($savedMessage->getId());
        $this->assertTrue($this->messageGateway->hasMessage($savedMessage->getId()));
    }

    public function testHandleSystemMessageWithEmptyMessage(): void
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

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');
        $this->conversationGateway->addConversation($conversation);

        $action = new SystemMessageAction(conversationId: 'conversation-id', message: '');

        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('Unable to retrieve system message from action');

        $realTimeUpdatePublisher
            ->expects($this->never())
            ->method('publishMessageUpdate');

        // Act
        ($this->handler)($action);

        // Assert
        $this->assertNull($this->conversationGateway->savedConversation);

        // Verify no message was saved through the message gateway since message was empty
        $this->assertCount(0, $this->messageGateway->getAll());
    }

    public function testHandleSystemMessageWithoutLogger(): void
    {
        $realTimeUpdatePublisher = $this->createMockWithExpectations(RealTimeUpdatePublisherInterface::class);
        $this->realTimeUpdatePublisher = $realTimeUpdatePublisher;

        // Arrange
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-id');
        $watchFile->setStatus(WatchFileStatus::DRAFT);

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');
        $this->conversationGateway->addConversation($conversation);

        $handlerWithoutLogger = new SystemMessageHandler(
            $this->conversationGateway,
            $this->messageGateway,
            $this->cache,
            $this->realTimeUpdatePublisher,
            new NullLlmOutputSanitizer(),
        );

        $action = new SystemMessageAction(conversationId: 'conversation-id', message: 'This is a system message');

        $realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate')
            ->with($this->isInstanceOf(Message::class));

        // Act
        ($handlerWithoutLogger)($action);

        // Assert
        $this->assertNull($this->conversationGateway->savedConversation);

        // Verify message was saved through the message gateway
        $this->assertCount(1, $this->messageGateway->getAll());
        $savedMessage = $this->messageGateway->getAll()[0];
        $this->assertInstanceOf(Message::class, $savedMessage);
        $this->assertEquals(MessageRole::System, $savedMessage->getRole());
        $this->assertEquals('This is a system message', $this->getMessageText($savedMessage));
        $this->assertNotNull($savedMessage->getId());
        $this->assertTrue($this->messageGateway->hasMessage($savedMessage->getId()));
    }

    public function testHandleSystemMessageWithComplexMessage(): void
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

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');
        $this->conversationGateway->addConversation($conversation);

        $complexMessage = "This is a complex system message with multiple lines.\nIt contains special characters: ���� and numbers: 123.\nIt also has punctuation marks: !@#$%^&*()";

        $action = new SystemMessageAction(conversationId: 'conversation-id', message: $complexMessage);

        $realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate')
            ->with($this->callback(function (Message $message) use ($complexMessage) {
                return MessageRole::System === $message->getRole()
                    && $this->getMessageText($message) === $complexMessage;
            }));

        $logger
            ->expects($this->once())
            ->method('info')
            ->with('System message added to conversation', $this->callback(function (array $context) {
                return 'conversation-id' === $context['conversation_id']
                    && isset($context['message_id'])
                    && \is_string($context['message_id']);
            }));

        // Act
        ($this->handler)($action);

        // Assert
        $this->assertNull($this->conversationGateway->savedConversation);

        // Verify message was saved through the message gateway
        $this->assertCount(1, $this->messageGateway->getAll());
        $savedMessage = $this->messageGateway->getAll()[0];
        $this->assertInstanceOf(Message::class, $savedMessage);
        $this->assertEquals(MessageRole::System, $savedMessage->getRole());
        $this->assertEquals($complexMessage, $this->getMessageText($savedMessage));
        $this->assertNotNull($savedMessage->getId());
        $this->assertTrue($this->messageGateway->hasMessage($savedMessage->getId()));
    }

    public function testHandleSystemMessageSucceedsWhenWatchFileIsEnabled(): void
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

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conversation-id');
        $this->conversationGateway->addConversation($conversation);

        $action = new SystemMessageAction(conversationId: 'conversation-id', message: 'This is a system message');

        $realTimeUpdatePublisher
            ->expects($this->once())
            ->method('publishMessageUpdate')
            ->with($this->callback(function (Message $message) {
                return MessageRole::System === $message->getRole()
                    && 'This is a system message' === $this->getMessageText($message);
            }));

        $logger
            ->expects($this->once())
            ->method('info');

        // Act
        ($this->handler)($action);

        // Assert
        $this->assertCount(1, $this->messageGateway->getAll());
        $savedMessage = $this->messageGateway->getAll()[0];
        $this->assertEquals(MessageRole::System, $savedMessage->getRole());
    }
}
