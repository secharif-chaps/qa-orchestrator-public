<?php

declare(strict_types=1);

namespace App\Tests\Integration\Chat;

use App\DataFixtures\Factory\Chat\ConversationFactory;
use App\DataFixtures\Factory\Chat\MessageFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Chat\MessageRole;
use App\Domain\Chat\MessageStatus;
use App\Tests\Integration\AbstractApiTestCase;

/**
 * Integration tests for Message status and retry API endpoints.
 *
 * Tests:
 * - Message GET includes status field
 * - Message GET includes retryCount field
 * - Conversation GET includes state field
 * - Retry POST endpoint works correctly
 */
class MessageStatusApiTest extends AbstractApiTestCase
{
    public function testGetMessageIncludesStatusField(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($user)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        // Create message with specific status
        MessageFactory::new()->create([
            'role' => MessageRole::User,
            'textContent' => 'Test message with status',
            'conversation' => $conversation,
            'status' => MessageStatus::Sent,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/conversations/' . $conversation->getId() . '/messages');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
        $this->assertNotEmpty($responseData['member']);

        $message = $responseData['member'][0];
        $this->assertArrayHasKey('status', $message);
        $this->assertEquals('sent', $message['status']);
    }

    public function testGetMessageIncludesRetryCountField(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($user)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        // Create message with specific retry count
        MessageFactory::new()->create([
            'role' => MessageRole::User,
            'textContent' => 'Test message with retry count',
            'conversation' => $conversation,
            'status' => MessageStatus::Error,
            'retryCount' => 2,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/conversations/' . $conversation->getId() . '/messages');

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
        $this->assertNotEmpty($responseData['member']);

        $message = $responseData['member'][0];
        $this->assertArrayHasKey('retryCount', $message);
        $this->assertEquals(2, $message['retryCount']);
    }

    public function testGetMessageStatusErrorState(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($user)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        // Create message in error status
        MessageFactory::new()->create([
            'role' => MessageRole::User,
            'textContent' => 'Failed message',
            'conversation' => $conversation,
            'status' => MessageStatus::Error,
            'retryCount' => 1,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/conversations/' . $conversation->getId() . '/messages');

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $message = $responseData['member'][0];

        $this->assertEquals('error', $message['status']);
        $this->assertEquals(1, $message['retryCount']);
    }

    public function testRetryMessageEndpoint(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($user)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        // Create message in error status that can be retried
        $message = MessageFactory::new()->create([
            'role' => MessageRole::User,
            'textContent' => 'Message to retry',
            'conversation' => $conversation,
            'status' => MessageStatus::Error,
            'retryCount' => 0,
        ]);

        $messageId = $message->getId();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', '/api/messages/' . $messageId . '/retry');

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('pending', $responseData['status']);
        $this->assertArrayHasKey('retryCount', $responseData);
        $this->assertEquals(1, $responseData['retryCount']);
    }

    public function testRetryMessageWithNonErrorStatusReturnsBadRequest(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($user)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        // Create message in sent status (not error)
        $message = MessageFactory::new()->create([
            'role' => MessageRole::User,
            'textContent' => 'Message in sent status',
            'conversation' => $conversation,
            'status' => MessageStatus::Sent,
        ]);

        $messageId = $message->getId();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', '/api/messages/' . $messageId . '/retry');

        $this->assertResponseStatusCodeSame(400);

        $responseData = $response->toArray(false);
        $this->assertStringContainsString('cannot be retried', $responseData['detail'] ?? '');
    }

    public function testRetryMessageExceedingMaxRetriesReturnsBadRequest(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($user)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        // Create message that has already reached max retry attempts (3)
        $message = MessageFactory::new()->create([
            'role' => MessageRole::User,
            'textContent' => 'Message at max retries',
            'conversation' => $conversation,
            'status' => MessageStatus::Error,
            'retryCount' => 3,
        ]);

        $messageId = $message->getId();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', '/api/messages/' . $messageId . '/retry');

        $this->assertResponseStatusCodeSame(400);

        $responseData = $response->toArray(false);
        $this->assertStringContainsString('maximum retry limit', $responseData['detail'] ?? '');
    }

    public function testRetryMessageWithNonExistentIdReturnsNotFound(): void
    {
        $user = UserFactory::new()->create();

        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', '/api/messages/' . $nonExistentId . '/retry');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testRetryMessageWithoutPermissionReturnsForbidden(): void
    {
        $messageOwner = UserFactory::new()->create();
        $otherUser = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($messageOwner)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $message = MessageFactory::new()->create([
            'role' => MessageRole::User,
            'textContent' => 'Message to retry',
            'conversation' => $conversation,
            'status' => MessageStatus::Error,
            'retryCount' => 0,
        ]);

        $messageId = $message->getId();

        // Try to retry with a different user
        $client = $this->createAuthenticatedClient($otherUser);
        $client->request('POST', '/api/messages/' . $messageId . '/retry');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRetryMessageUpdatesUpdatedAtAndUpdatedBy(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($user)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        // Create message in error status
        $message = MessageFactory::new()->create([
            'role' => MessageRole::User,
            'textContent' => 'Message to retry',
            'conversation' => $conversation,
            'status' => MessageStatus::Error,
            'retryCount' => 0,
        ]);

        $messageId = $message->getId();
        $userId = $user->getId();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', '/api/messages/' . $messageId . '/retry');

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertEquals('pending', $responseData['status']);

        // Verify updatedAt and updatedBy via API response
        $this->assertArrayHasKey('updatedAt', $responseData);
        $this->assertNotNull($responseData['updatedAt']);

        $this->assertArrayHasKey('updatedBy', $responseData);
        $this->assertIsArray($responseData['updatedBy']);
        $this->assertEquals($userId, $responseData['updatedBy']['id']);
    }
}
