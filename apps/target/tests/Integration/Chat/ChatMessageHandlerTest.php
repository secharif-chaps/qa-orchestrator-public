<?php

declare(strict_types=1);

namespace App\Tests\Integration\Chat;

use App\Application\Chat\ErrorModelMessageAction;
use App\Application\Chat\ModelMessageAction;
use App\Application\Chat\SystemMessageAction;
use App\DataFixtures\Factory\Chat\ConversationFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Chat\Content\TextContent;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use App\Domain\WatchFile\WatchFileStatus;
use App\Tests\Integration\AbstractApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

/**
 * Integration tests for Chat message handlers via MessageBus.
 *
 * These tests verify the complete flow from N8N workflow:
 * 1. Dispatch action to MessageBus
 * 2. Message is queued in async_priority_low transport
 * 3. Process the queue
 * 4. Database state is correct after processing
 *
 * Tests cover ModelMessageHandler, SystemMessageHandler, and ErrorModelMessageHandler.
 */
class ChatMessageHandlerTest extends AbstractApiTestCase
{
    use Factories;
    use InteractsWithMessenger;
    use ResetDatabase;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->getContainer()
            ->get(EntityManagerInterface::class);
    }

    private function refreshConversation(string $conversationId): Conversation
    {
        $this->entityManager->clear();
        $conversation = $this->entityManager->find(Conversation::class, $conversationId);
        $this->assertNotNull($conversation, 'Conversation should exist');

        return $conversation;
    }

    private function getMessageTextContent(Message $message): ?string
    {
        foreach ($message->getContents() as $content) {
            if ($content instanceof TextContent) {
                return $content->getContent();
            }
        }

        return null;
    }

    /**
     * @return array<string, array{message: string, expectedContent: string}>
     */
    public static function modelMessageContentProvider(): array
    {
        return [
            'simple message' => [
                'message' => 'This is a model response from the AI assistant.',
                'expectedContent' => 'This is a model response from the AI assistant.',
            ],
            'message with whitespace' => [
                'message' => '   Message with whitespace   ',
                'expectedContent' => 'Message with whitespace',
            ],
            'unicode content' => [
                'message' => 'Réponse du modèle avec émojis 🎯 et accents: éàüöñ €£¥',
                'expectedContent' => 'Réponse du modèle avec émojis 🎯 et accents: éàüöñ €£¥',
            ],
        ];
    }

    #[DataProvider('modelMessageContentProvider')]
    public function testModelMessageHandlerAddMessage(string $message, string $expectedContent): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();
        $this->assertNotNull($conversationId);

        $action = new ModelMessageAction(conversationId: $conversationId, message: $message);

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->queue()
            ->assertContains(ModelMessageAction::class);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();
        $this->transport('async_priority_low')
            ->queue()
            ->assertEmpty();

        $updatedConversation = $this->refreshConversation($conversationId);
        $messages = $updatedConversation->getMessages();

        $this->assertCount(1, $messages);
        $messageEntity = $messages->first();
        $this->assertInstanceOf(Message::class, $messageEntity);
        $this->assertEquals($expectedContent, $this->getMessageTextContent($messageEntity));
        $this->assertEquals(MessageRole::Model, $messageEntity->getRole());
    }

    public function testModelMessageHandlerSkipsEmptyMessage(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();
        $this->assertNotNull($conversationId);

        $action = new ModelMessageAction(
            conversationId: $conversationId,
            message: '   ' // Empty after trim
        );

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        $updatedConversation = $this->refreshConversation($conversationId);
        $messages = $updatedConversation->getMessages();

        // Empty message should not be added
        $this->assertCount(0, $messages);
    }

    /**
     * @return array<string, array{message: string, expectedContent: string}>
     */
    public static function systemMessageContentProvider(): array
    {
        return [
            'simple notification' => [
                'message' => 'This is a system notification message.',
                'expectedContent' => 'This is a system notification message.',
            ],
            'unicode notification' => [
                'message' => 'Notification système avec émojis 📢 et accents: éàüöñ',
                'expectedContent' => 'Notification système avec émojis 📢 et accents: éàüöñ',
            ],
        ];
    }

    #[DataProvider('systemMessageContentProvider')]
    public function testSystemMessageHandlerAddMessage(string $message, string $expectedContent): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();
        $this->assertNotNull($conversationId);

        $action = new SystemMessageAction(conversationId: $conversationId, message: $message);

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->queue()
            ->assertContains(SystemMessageAction::class);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();
        $this->transport('async_priority_low')
            ->queue()
            ->assertEmpty();

        $updatedConversation = $this->refreshConversation($conversationId);
        $messages = $updatedConversation->getMessages();

        $this->assertCount(1, $messages);
        $messageEntity = $messages->first();
        $this->assertInstanceOf(Message::class, $messageEntity);
        $this->assertEquals($expectedContent, $this->getMessageTextContent($messageEntity));
        $this->assertEquals(MessageRole::System, $messageEntity->getRole());
    }

    public function testSystemMessageHandlerSkipsEmptyMessage(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();
        $this->assertNotNull($conversationId);

        $action = new SystemMessageAction(
            conversationId: $conversationId,
            message: '' // Empty message
        );

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        $updatedConversation = $this->refreshConversation($conversationId);
        $messages = $updatedConversation->getMessages();

        // Empty message should not be added
        $this->assertCount(0, $messages);
    }

    /**
     * @return array<string, array{error: string, context: array<string, mixed>}>
     */
    public static function errorMessageProvider(): array
    {
        return [
            'simple error' => [
                'error' => 'Model timeout error',
                'context' => [],
            ],
            'error with context' => [
                'error' => 'Rate limit exceeded',
                'context' => [
                    'retry_after' => 60,
                    'request_id' => 'req-12345',
                ],
            ],
            'api connection error' => [
                'error' => 'API connection failed',
                'context' => [
                    'endpoint' => 'https://api.example.com',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('errorMessageProvider')]
    public function testErrorModelMessageHandlerAddError(string $error, array $context): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();
        $this->assertNotNull($conversationId);

        $action = new ErrorModelMessageAction(
            error: $error,
            messageId: '550e8400-e29b-41d4-a716-446655440000',
            conversationId: $conversationId,
            watchFileId: $watchFileId,
            context: $context
        );

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->queue()
            ->assertContains(ErrorModelMessageAction::class);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();
        $this->transport('async_priority_low')
            ->queue()
            ->assertEmpty();

        $updatedConversation = $this->refreshConversation($conversationId);
        $messages = $updatedConversation->getMessages();

        $this->assertCount(1, $messages);
        $messageEntity = $messages->first();
        $this->assertInstanceOf(Message::class, $messageEntity);
        $this->assertEquals(MessageRole::SystemError, $messageEntity->getRole());

        // Error info should be in metadata
        $metadata = $messageEntity->getMetadata();
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('error', $metadata);
        $this->assertEquals($error, $metadata['error']);
    }

    public function testErrorModelMessageHandlerWithNullConversationIdUsesLastConversation(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Create a conversation for this watchfile
        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();
        $this->assertNotNull($conversationId);

        $action = new ErrorModelMessageAction(
            error: 'API connection failed',
            messageId: null,
            conversationId: null, // Should use last conversation for watchFile
            watchFileId: $watchFileId
        );

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        // Error should be added to the last conversation for this watchFile
        $updatedConversation = $this->refreshConversation($conversationId);
        $messages = $updatedConversation->getMessages();

        $this->assertCount(1, $messages);
        $messageEntity = $messages->first();
        $this->assertNotFalse($messageEntity);
        $this->assertEquals(MessageRole::SystemError, $messageEntity->getRole());
    }

    public function testMultipleMessagesInConversation(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();
        $this->assertNotNull($conversationId);

        // Process messages sequentially, resetting conversation state between model responses
        // (simulates real flow: agent responds → conversation goes idle → user asks → waiting again)
        $this->bus()
->dispatch(new ModelMessageAction(conversationId: $conversationId, message: 'Model response 1'));
        $this->transport('async_priority_low')
->throwExceptions()
->process();

        // Conversation is now Idle after model response; set back to WaitingForAgent for next message
        $conv = $this->refreshConversation($conversationId);
        $conv->setState(\App\Domain\Chat\ConversationState::WaitingForAgent);
        $this->entityManager->flush();

        $this->bus()
->dispatch(new SystemMessageAction(conversationId: $conversationId, message: 'System notification'));
        $this->transport('async_priority_low')
->throwExceptions()
->process();

        $this->bus()
->dispatch(new ModelMessageAction(conversationId: $conversationId, message: 'Model response 2'));
        $this->transport('async_priority_low')
->throwExceptions()
->process();

        $this->transport('async_priority_low')
->queue()
->assertEmpty();

        $updatedConversation = $this->refreshConversation($conversationId);
        $messages = $updatedConversation->getMessages();

        $this->assertCount(3, $messages);

        $roles = array_map(static fn ($m) => $m->getRole(), $messages->toArray());

        $this->assertContains(MessageRole::Model, $roles);
        $this->assertContains(MessageRole::System, $roles);
    }
}
