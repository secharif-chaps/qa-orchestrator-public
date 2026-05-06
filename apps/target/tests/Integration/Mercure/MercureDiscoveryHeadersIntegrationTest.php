<?php

declare(strict_types=1);

namespace App\Tests\Integration\Mercure;

use App\DataFixtures\Factory\Chat\ConversationFactory;
use App\DataFixtures\Factory\Chat\MessageFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Chat\MessageRole;
use App\Infrastructure\Mercure\MercureDiscoverySubscriber;
use App\Tests\Integration\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for Mercure discovery Link headers.
 *
 * Verifies that the MercureDiscoverySubscriber adds proper RFC 8288 Link headers
 * to API responses for supported resources, enabling clients to discover
 * the Mercure hub URL and subscription topics.
 */
#[CoversClass(MercureDiscoverySubscriber::class)]
class MercureDiscoveryHeadersIntegrationTest extends AbstractApiTestCase
{
    public function testGetWatchFileReturnsDiscoveryHeaders(): void
    {
        $owner = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $watchFileId = $watchFile->getId();
        $ownerId = $owner->getId();

        $response = $client->request('GET', "/api/watch_files/{$watchFileId}");

        $this->assertResponseIsSuccessful();

        // Verify Link headers are present (API Platform also adds its own Link header for API docs)
        $linkHeaders = $response->getHeaders()['link'] ?? [];
        $this->assertGreaterThanOrEqual(2, \count($linkHeaders), 'Should have at least 2 Link headers (hub + topic)');

        // Check Mercure hub link
        $hubLinks = array_filter($linkHeaders, fn ($h) => str_contains($h, 'rel="mercure"'));
        $this->assertCount(1, $hubLinks, 'Should have exactly one mercure hub link');
        $hubLink = reset($hubLinks);
        $this->assertIsString($hubLink);
        $this->assertMatchesRegularExpression('/^<[^>]+>;\s*rel="mercure"$/', $hubLink);

        // Check topic link
        $topicLinks = array_filter($linkHeaders, fn ($h) => str_contains($h, 'rel="topic"'));
        $this->assertCount(1, $topicLinks, 'Should have exactly one topic link');
        $topicLink = reset($topicLinks);

        // Verify topic format: /users/{userId}/watch-files/{watchFileId}
        $expectedTopicPattern = \sprintf(
            '#</users/%s/watch-files/%s>;\s*rel="topic"#',
            preg_quote((string) $ownerId),
            preg_quote($watchFileId),
        );
        $this->assertIsString($topicLink);
        $this->assertMatchesRegularExpression($expectedTopicPattern, $topicLink);
    }

    public function testGetConversationMessagesReturnsDiscoveryHeaders(): void
    {
        $owner = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();
        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();
        MessageFactory::new()
            ->with([
                'conversation' => $conversation,
                'role' => MessageRole::User,
                'textContent' => 'Test message',
            ])
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $conversationId = $conversation->getId();
        $ownerId = $owner->getId();

        $response = $client->request('GET', "/api/conversations/{$conversationId}/messages");

        $this->assertResponseIsSuccessful();

        // Verify Link headers are present (API Platform also adds its own Link header)
        $linkHeaders = $response->getHeaders()['link'] ?? [];
        $this->assertGreaterThanOrEqual(2, \count($linkHeaders));

        // Check topic link format: /users/{userId}/conversations/{conversationId}/messages
        $topicLinks = array_filter($linkHeaders, fn ($h) => str_contains($h, 'rel="topic"'));
        $this->assertCount(1, $topicLinks, 'Should have exactly one topic link');
        $topicLink = reset($topicLinks);
        $this->assertIsString($topicLink);

        $expectedTopicPattern = \sprintf(
            '#</users/%s/conversations/%s/messages>;\s*rel="topic"#',
            preg_quote((string) $ownerId),
            preg_quote((string) $conversationId)
        );
        $this->assertMatchesRegularExpression($expectedTopicPattern, $topicLink);
    }

    public function testGetLastConversationReturnsDiscoveryHeadersWithConversationIdFromBody(): void
    {
        $owner = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();
        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $watchFileId = $watchFile->getId();
        $conversationId = $conversation->getId();
        $ownerId = $owner->getId();

        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/conversations/last");

        $this->assertResponseIsSuccessful();

        // Verify the response body contains the conversation ID
        $responseData = $response->toArray();
        $this->assertEquals((string) $conversationId, $responseData['id']);

        // Verify Link headers are present (API Platform also adds its own Link header)
        $linkHeaders = $response->getHeaders()['link'] ?? [];
        $this->assertGreaterThanOrEqual(2, \count($linkHeaders), 'Should have at least 2 Link headers (hub + topic)');

        // Check topic link uses the conversation ID from the response body (not the watchfile ID)
        $topicLinks = array_filter($linkHeaders, fn ($h) => str_contains($h, 'rel="topic"'));
        $this->assertCount(1, $topicLinks, 'Should have exactly one topic link');
        $topicLink = reset($topicLinks);
        $this->assertIsString($topicLink);

        // The topic should be: /users/{userId}/conversations/{conversationId}
        // NOT: /users/{userId}/watch-files/{watchFileId}
        $expectedTopicPattern = \sprintf(
            '#</users/%s/conversations/%s>;\s*rel="topic"#',
            preg_quote((string) $ownerId),
            preg_quote((string) $conversationId)
        );
        $this->assertMatchesRegularExpression(
            $expectedTopicPattern,
            $topicLink,
            'Topic should reference conversation ID extracted from response body'
        );
    }

    public function testNoDiscoveryHeadersForUnsupportedRoutes(): void
    {
        $owner = UserFactory::new()->create();
        WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        // Request to a route that doesn't support discovery headers
        $response = $client->request('GET', '/api/watch_files');

        $this->assertResponseIsSuccessful();

        // Verify no Mercure Link headers
        $linkHeaders = $response->getHeaders()['link'] ?? [];
        $mercureLinks = array_filter($linkHeaders, fn ($h) => str_contains($h, 'rel="mercure"'));
        $this->assertEmpty($mercureLinks, 'Collection endpoints should not have Mercure discovery headers');
    }

    public function testNoDiscoveryHeadersForUnauthorizedRequests(): void
    {
        $owner = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        // Create unauthenticated client
        $client = self::createClient();

        $watchFileId = $watchFile->getId();

        $response = $client->request('GET', "/api/watch_files/{$watchFileId}");

        // Should be unauthorized
        $this->assertResponseStatusCodeSame(401);

        // Verify no Mercure Link headers on error response
        $linkHeaders = $response->getHeaders(false)['link'] ?? [];
        $mercureLinks = array_filter($linkHeaders, fn ($h) => str_contains($h, 'rel="mercure"'));
        $this->assertEmpty($mercureLinks, 'Error responses should not have Mercure discovery headers');
    }

    public function testDiscoveryHeadersAreUserScoped(): void
    {
        $owner = UserFactory::new()->create();
        $otherUser = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        // First user request
        $client1 = $this->createAuthenticatedClient($owner);
        $watchFileId = $watchFile->getId();
        $ownerId = $owner->getId();

        $response1 = $client1->request('GET', "/api/watch_files/{$watchFileId}");
        $this->assertResponseIsSuccessful();

        $linkHeaders1 = $response1->getHeaders()['link'] ?? [];
        $topicLinks1 = array_filter($linkHeaders1, fn ($h) => str_contains($h, 'rel="topic"'));
        $topicLink1 = reset($topicLinks1);
        $this->assertIsString($topicLink1);

        // Verify topic contains the owner's user ID
        $this->assertStringContainsString((string) $ownerId, $topicLink1);
        $this->assertStringNotContainsString((string) $otherUser->getId(), $topicLink1);
    }
}
