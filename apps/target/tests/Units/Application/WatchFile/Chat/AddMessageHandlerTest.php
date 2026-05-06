<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\Chat;

use App\Application\WatchFile\Chat\AddMessageAction;
use App\Application\WatchFile\Chat\AddMessageHandler;
use App\Domain\Chat\Content\TextContent;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileActiveException;
use App\Domain\WatchFile\WatchFileStatus;
use App\Tests\Units\Infrastructure\AI\NullChatSession;
use App\Tests\Units\Infrastructure\Chat\NullConversationGateway;
use App\Tests\Units\Infrastructure\Chat\NullMessageGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\TestCase;

class AddMessageHandlerTest extends TestCase
{
    use EntityUtilsTrait;

    private function getMessageText(Message $message): string
    {
        foreach ($message->getContents() as $content) {
            if ($content instanceof TextContent) {
                return $content->getContent();
            }
        }

        return '';
    }

    public function testAddMessageToConversation(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watchfile_id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conv_id');

        $conversationGateway = new NullConversationGateway();
        $conversationGateway->addConversation($conversation);
        $messageGateway = new NullMessageGateway();
        $chatSession = new NullChatSession();
        $realTimeUpdatePublisher = $this->createMock(RealTimeUpdatePublisherInterface::class);
        $realTimeUpdatePublisher->expects($this->once())
            ->method('publishMessageUpdate')
            ->with($this->isInstanceOf(Message::class));

        $handler = new AddMessageHandler($conversationGateway, $messageGateway, $chatSession, $realTimeUpdatePublisher);

        $action = new AddMessageAction('conv_id', 'Hello world');
        $result = ($handler)($action);

        $this->assertInstanceOf(Conversation::class, $result);
        $this->assertCount(0, $result->getMessages());

        // Test that the message was saved in the message gateway
        $this->assertCount(1, $messageGateway->getAll());
        $savedMessage = $messageGateway->getAll()[0];
        $this->assertEquals('Hello world', $this->getMessageText($savedMessage));

        // Test that the message has been sent with the chat session
        $this->assertNotNull($chatSession->getLastSentMessage());
        $this->assertSame($savedMessage, $chatSession->getLastSentMessage());
        $this->assertSame($conversation, $chatSession->getLastSentConversation());
        $this->assertEquals('Hello world', $this->getMessageText($chatSession->getLastSentMessage()));
    }

    public function testAddMessageToConversationWithDraftStatus(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watchfile_id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conv_id');

        $conversationGateway = new NullConversationGateway();
        $conversationGateway->addConversation($conversation);
        $messageGateway = new NullMessageGateway();
        $chatSession = new NullChatSession();
        $realTimeUpdatePublisher = $this->createMock(RealTimeUpdatePublisherInterface::class);
        $realTimeUpdatePublisher->expects($this->once())
            ->method('publishMessageUpdate')
            ->with($this->isInstanceOf(Message::class));

        $handler = new AddMessageHandler($conversationGateway, $messageGateway, $chatSession, $realTimeUpdatePublisher);

        $action = new AddMessageAction('conv_id', 'Test message in draft status');
        $result = ($handler)($action);

        $this->assertInstanceOf(Conversation::class, $result);
        $this->assertCount(0, $result->getMessages());

        // Test that the message was saved in the message gateway
        $this->assertCount(1, $messageGateway->getAll());
        $savedMessage = $messageGateway->getAll()[0];
        $this->assertEquals('Test message in draft status', $this->getMessageText($savedMessage));
    }

    public function testAddMessageToConversationWithEnabledStatusThrowsException(): void
    {
        $this->expectException(WatchFileActiveException::class);
        $this->expectExceptionMessage(
            'Cannot add messages to conversation on watchfile watchfile_id because it is in active status. Please set the watchfile to draft mode first.'
        );

        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watchfile_id');
        $watchFile->setStatus(WatchFileStatus::ENABLED);

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'conv_id');

        $conversationGateway = new NullConversationGateway();
        $conversationGateway->addConversation($conversation);
        $messageGateway = new NullMessageGateway();
        $chatSession = new NullChatSession();
        $realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);

        $handler = new AddMessageHandler($conversationGateway, $messageGateway, $chatSession, $realTimeUpdatePublisher);

        $action = new AddMessageAction('conv_id', 'This should fail');
        ($handler)($action);
    }
}
