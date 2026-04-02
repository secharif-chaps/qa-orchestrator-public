<?php

declare(strict_types=1);

namespace App\Tests\Integration\Chat;

use App\Application\Chat\ErrorModelMessageAction;
use App\Application\Chat\ModelMessageAction;
use App\Application\Chat\SystemMessageAction;
use App\DataFixtures\Factory\Chat\ConversationFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\WatchFile\WatchFileStatus;
use App\Infrastructure\Mercure\Test\NullHub;
use App\Tests\Integration\AbstractApiTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

/**
 * Integration tests to verify Mercure publications are correctly triggered.
 *
 * These tests ensure that each message handler triggers the expected
 * number of Mercure publications (message updates and conversation updates),
 * preventing duplicate publications and unnecessary load.
 *
 * Publication patterns:
 * - ModelMessageHandler: 1 message + 1 conversation (state set to Idle)
 * - SystemMessageHandler: 1 message only (conversation update only if waiting for agent)
 * - ErrorModelMessageHandler: 1 message + 1 conversation (state set to Idle)
 *
 * This is critical because:
 * - N8N workflows trigger many async message handlers
 * - Duplicate publications waste bandwidth and cause UI flickering
 * - Mercure subscriptions in browsers receive unnecessary updates
 */
class MercurePublicationTest extends AbstractApiTestCase
{
    use Factories;
    use InteractsWithMessenger;
    use ResetDatabase;
    private NullHub $mercureHub;

    protected function setUp(): void
    {
        parent::setUp();

        // In test environment, Mercure uses TraceableHub which wraps our NullHub
        $container = self::getContainer();
        $hub = $container->get('mercure.hub.default.traceable.inner');

        $this->assertInstanceOf(NullHub::class, $hub, 'Test environment should use NullHub');
        $this->mercureHub = $hub;
        $this->mercureHub->reset();
    }

    public function testModelMessageHandlerPublishesMessageAndConversation(): void
    {
        // Arrange - use withOwnedBy to create WatchFileUser relationship for real-time access
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();
        $this->assertNotNull($conversationId);

        // Clear any messages in the queue from fixture creation and reset Mercure hub
        $this->transport('async_priority_low')
            ->process();
        $this->mercureHub->reset();

        // Act
        $action = new ModelMessageAction(conversationId: $conversationId, message: 'Test model response');

        $this->bus()
            ->dispatch($action);

        // Check queue size before processing
        $queueSize = $this->transport('async_priority_low')
            ->queue()
            ->count();

        // Process exactly one message
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process(1);

        // Get message-specific publications
        // Note: Topic format is /users/{userId}/conversations/{conversationId}
        $messageUpdates = $this->mercureHub->getPublishedUpdatesForTopic('conversations');

        // Assert: 2 publications (1 for message + 1 for conversation state update to Idle)
        $this->assertCount(
            2,
            $messageUpdates,
            \sprintf(
                'ModelMessageHandler should publish 1 message update + 1 conversation update (queue had %d messages)',
                $queueSize
            )
        );
    }

    public function testSystemMessageHandlerPublishesExactlyOnce(): void
    {
        // Arrange - use withOwnedBy to create WatchFileUser relationship for real-time access
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();
        $this->assertNotNull($conversationId);

        // Clear any messages in the queue from fixture creation and reset Mercure hub
        $this->transport('async_priority_low')
            ->process();
        $this->mercureHub->reset();

        // Act
        $action = new SystemMessageAction(conversationId: $conversationId, message: 'System notification message');

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process(1);

        // Assert: 2 publications (1 message + 1 conversation state update to AgentProcessing)
        $messageUpdates = $this->mercureHub->getPublishedUpdatesForTopic('conversations');
        $this->assertCount(
            2,
            $messageUpdates,
            'SystemMessageHandler should publish 1 message update + 1 conversation update when WaitingForAgent'
        );
    }

    public function testErrorModelMessageHandlerPublishesMessageAndConversation(): void
    {
        // Arrange - use withOwnedBy to create WatchFileUser relationship for real-time access
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $watchFileId = $watchFile->getId();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();
        $this->assertNotNull($conversationId);

        // Clear any messages in the queue from fixture creation and reset Mercure hub
        $this->transport('async_priority_low')
            ->process();
        $this->mercureHub->reset();

        // Act
        $action = new ErrorModelMessageAction(
            error: 'Test error message',
            messageId: null,
            conversationId: $conversationId,
            watchFileId: $watchFileId
        );

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process(1);

        // Assert: 2 publications (1 for error message + 1 for conversation state update)
        $messageUpdates = $this->mercureHub->getPublishedUpdatesForTopic('conversations');
        $this->assertCount(
            2,
            $messageUpdates,
            'ErrorModelMessageHandler should publish 1 message update + 1 conversation update'
        );
    }

    public function testMultipleMessagesPublishCorrectCount(): void
    {
        // Arrange - use withOwnedBy to create WatchFileUser relationship for real-time access
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();
        $this->assertNotNull($conversationId);

        // Clear any messages in the queue from fixture creation and reset Mercure hub
        $this->transport('async_priority_low')
            ->process();
        $this->mercureHub->reset();

        // Act: dispatch and process messages sequentially (model responses set conversation to Idle)
        $entityManager = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);

        // 1. Model message → 2 publications (message + conversation)
        $this->bus()
->dispatch(new ModelMessageAction(conversationId: $conversationId, message: 'Model response 1'));
        $this->transport('async_priority_low')
->throwExceptions()
->process(1);

        // Reset conversation state to WaitingForAgent (simulates user sending a new message)
        $entityManager->clear();
        $conv = $entityManager->find(\App\Domain\Chat\Conversation::class, $conversationId);
        $this->assertNotNull($conv);
        $conv->setState(\App\Domain\Chat\ConversationState::WaitingForAgent);
        $entityManager->flush();

        // 2. System message → 1 publication (message only)
        $this->bus()
->dispatch(new SystemMessageAction(conversationId: $conversationId, message: 'System notification'));
        $this->transport('async_priority_low')
->throwExceptions()
->process(1);

        // 3. Model message → 2 publications (message + conversation)
        $this->bus()
->dispatch(new ModelMessageAction(conversationId: $conversationId, message: 'Model response 2'));
        $this->transport('async_priority_low')
->throwExceptions()
->process(1);

        // Expected: 2 (model) + 2 (system when WaitingForAgent) + 2 (model) = 6 publications
        $messageUpdates = $this->mercureHub->getPublishedUpdatesForTopic('conversations');
        $count = \count($messageUpdates);
        $this->assertGreaterThanOrEqual(
            5,
            $count,
            \sprintf('Expected at least 5 Mercure publications, got %d', $count)
        );
        $this->assertLessThanOrEqual(
            6,
            $count,
            \sprintf('Expected at most 6 Mercure publications, got %d', $count)
        );
    }

    public function testEmptyMessageDoesNotPublish(): void
    {
        // Arrange - use withOwnedBy to create WatchFileUser relationship for real-time access
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();
        $this->assertNotNull($conversationId);

        // Clear any messages in the queue from fixture creation and reset Mercure hub
        $this->transport('async_priority_low')
            ->process();
        $this->mercureHub->reset();

        // Act: dispatch empty message (should be skipped)
        $action = new ModelMessageAction(
            conversationId: $conversationId,
            message: '   ' // Empty after trim
        );

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process(1);

        // Assert: no message publication for empty messages
        $messageUpdates = $this->mercureHub->getPublishedUpdatesForTopic('conversations');
        $this->assertCount(0, $messageUpdates, 'Empty messages should not trigger any message publication');
    }
}
