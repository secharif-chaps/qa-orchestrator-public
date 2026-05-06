<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Chat\ConversationFactory;
use App\DataFixtures\Factory\Chat\MessageFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\MessageRole;
use App\Domain\User\User;

class ConversationMessagesApiTest extends AbstractApiTestCase
{
    public function testGetConversationMessages(): void
    {
        $conversationOwner = UserFactory::new()
            ->create();

        $conversation = $this->createConversationWithMessages(
            5,
            $conversationOwner,
            MessageRole::User,
            'Test message'
        );

        $client = $this->createAuthenticatedClient($conversationOwner);
        $response = $client->request('GET', '/api/conversations/' . $conversation->getId() . '/messages');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
        $this->assertCount(5, $responseData['member']);
    }

    public function testGetConversationMessagesWithPagination(): void
    {
        $conversationOwner = UserFactory::new()
            ->create();

        $conversation = $this->createConversationWithMessages(
            10,
            $conversationOwner,
            MessageRole::User,
            'Test message'
        );

        $client = $this->createAuthenticatedClient($conversationOwner);
        // No need to specify order - default is DESC by cursor
        $response = $client->request(
            'GET',
            '/api/conversations/' . $conversation->getId() . '/messages?itemsPerPage=5'
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
        $this->assertArrayHasKey('view', $responseData);

        // Should return first page with 5 items (explicit itemsPerPage)
        $this->assertCount(5, $responseData['member']);

        // Check pagination metadata - cursor pagination always provides both next and previous links
        // (partial pagination doesn't check if there are actually more items)
        $this->assertArrayHasKey('next', $responseData['view']);
        $this->assertArrayHasKey('previous', $responseData['view']);

        // Verify next URL contains the cursor parameter
        $this->assertStringContainsString('cursor', $responseData['view']['next']);

        // Verify message ordering (newest first)
        $messages = $responseData['member'];
        $this->assertGreaterThan(
            $messages[1]['createdAt'] ?? '',
            $messages[0]['createdAt'] ?? '',
            'Messages should be ordered by creation date descending'
        );
    }

    public function testGetConversationMessagesCursorPaginationUrlsGenerated(): void
    {
        $conversationOwner = UserFactory::new()
            ->create();

        $conversation = $this->createConversationWithMessages(
            8,
            $conversationOwner,
            MessageRole::User,
            'Test message'
        );

        $client = $this->createAuthenticatedClient($conversationOwner);

        // Get first page
        $firstPageResponse = $client->request(
            'GET',
            '/api/conversations/' . $conversation->getId() . '/messages?itemsPerPage=5'
        );
        $firstPageData = $firstPageResponse->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertCount(5, $firstPageData['member']);

        // Verify cursor pagination URLs are generated
        $this->assertArrayHasKey('view', $firstPageData);
        $this->assertArrayHasKey('next', $firstPageData['view']);
        $this->assertArrayHasKey('previous', $firstPageData['view']);

        // Verify the cursor parameter format (cursor[lt] for next, cursor[gt] for previous)
        $this->assertStringContainsString('cursor', $firstPageData['view']['next']);
        $this->assertStringContainsString('cursor', $firstPageData['view']['previous']);

        // Verify cursor URL contains numeric value (microseconds since Unix epoch)
        $this->assertMatchesRegularExpression('/cursor%5B(lt|gt)%5D=\d+/', $firstPageData['view']['next']);

        // Verify following cursor URL returns successful response
        $nextPageResponse = $client->request('GET', $firstPageData['view']['next']);
        $this->assertResponseIsSuccessful();
    }

    public function testGetConversationMessagesWithCustomItemsPerPage(): void
    {
        $conversationOwner = UserFactory::new()
            ->create();

        $conversation = $this->createConversationWithMessages(
            20,
            $conversationOwner,
            MessageRole::User,
            'Test message'
        );

        $client = $this->createAuthenticatedClient($conversationOwner);
        $response = $client->request(
            'GET',
            '/api/conversations/' . $conversation->getId() . '/messages?itemsPerPage=10'
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
        $this->assertCount(10, $responseData['member']);
    }

    public function testGetConversationMessagesWithInvalidConversationId(): void
    {
        $conversationOwner = UserFactory::new()
            ->create();

        $client = $this->createAuthenticatedClient($conversationOwner);
        $response = $client->request('GET', '/api/conversations/invalid-uuid/messages');

        $this->assertResponseStatusCodeSame(500);
    }

    public function testGetConversationMessagesWithNonExistentConversation(): void
    {
        $conversationOwner = UserFactory::new()
            ->create();

        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';
        $client = $this->createAuthenticatedClient($conversationOwner);
        $client->request('GET', '/api/conversations/' . $nonExistentId . '/messages');

        $this->assertResponseStatusCodeSame(expectedCode: 404);
    }

    public function testGetConversationMessagesWithEmptyConversation(): void
    {
        $conversationOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($conversationOwner)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($conversationOwner);
        $response = $client->request('GET', '/api/conversations/' . $conversation->getId() . '/messages');

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);
        $this->assertCount(0, $responseData['member']);
    }

    public function testCursorPaginationLinksAreValid(): void
    {
        $conversationOwner = UserFactory::new()
            ->create();

        $conversation = $this->createConversationWithMessages(
            9,
            $conversationOwner,
            MessageRole::User,
            'Test message'
        );

        $client = $this->createAuthenticatedClient($conversationOwner);

        // Get first page
        $firstPageResponse = $client->request(
            'GET',
            '/api/conversations/' . $conversation->getId() . '/messages?itemsPerPage=3'
        );
        $firstPageData = $firstPageResponse->toArray();
        $this->assertCount(3, $firstPageData['member']);

        // Verify cursor links are present
        $this->assertArrayHasKey('view', $firstPageData);
        $this->assertArrayHasKey('next', $firstPageData['view']);
        $this->assertArrayHasKey('previous', $firstPageData['view']);

        // Navigate to next page - should return successful response
        $secondPageResponse = $client->request('GET', $firstPageData['view']['next']);
        $this->assertResponseIsSuccessful();
        $secondPageData = $secondPageResponse->toArray();

        // Verify second page also has cursor links
        $this->assertArrayHasKey('view', $secondPageData);
        $this->assertArrayHasKey('next', $secondPageData['view']);
        $this->assertArrayHasKey('previous', $secondPageData['view']);

        // Navigate using previous link - should return successful response
        $backResponse = $client->request('GET', $secondPageData['view']['previous']);
        $this->assertResponseIsSuccessful();
    }

    public function testCursorPaginationWithDefaultDescOrder(): void
    {
        $conversationOwner = UserFactory::new()
            ->create();

        $conversation = $this->createConversationWithMessages(
            5,
            $conversationOwner,
            MessageRole::User,
            'Test message'
        );

        $client = $this->createAuthenticatedClient($conversationOwner);

        // Request without explicit order - should default to DESC
        $response = $client->request('GET', '/api/conversations/' . $conversation->getId() . '/messages');

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        // Verify messages are ordered by createdAt descending (newest first)
        $messages = $responseData['member'];
        for ($i = 0; $i < \count($messages) - 1; ++$i) {
            $this->assertGreaterThan(
                $messages[$i + 1]['createdAt'],
                $messages[$i]['createdAt'],
                'Messages should be ordered by creation date descending by default'
            );
        }
    }

    public function testCursorPaginationNextPageReturnsRemainingMessages(): void
    {
        $conversationOwner = UserFactory::new()
            ->create();

        // Create a conversation with 15 messages to test pagination across multiple pages
        $conversation = $this->createConversationWithMessages(
            15,
            $conversationOwner,
            MessageRole::User,
            'Test message'
        );

        $client = $this->createAuthenticatedClient($conversationOwner);

        // Get first page with 5 items per page
        $firstPageResponse = $client->request(
            'GET',
            '/api/conversations/' . $conversation->getId() . '/messages?itemsPerPage=5'
        );
        $firstPageData = $firstPageResponse->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('member', $firstPageData);
        $this->assertCount(5, $firstPageData['member']);

        // Store first page message IDs to verify no duplicates
        $firstPageMessageIds = array_column($firstPageData['member'], 'id');

        // Verify first page messages are ordered correctly (newest first)
        $firstPageMessages = $firstPageData['member'];
        for ($i = 0; $i < \count($firstPageMessages) - 1; ++$i) {
            $this->assertGreaterThan(
                $firstPageMessages[$i + 1]['createdAt'],
                $firstPageMessages[$i]['createdAt'],
                'First page messages should be ordered by creation date descending'
            );
        }

        // Verify next page link exists
        $this->assertArrayHasKey('view', $firstPageData);
        $this->assertArrayHasKey('next', $firstPageData['view']);

        // Get second page using the next link
        $secondPageResponse = $client->request('GET', $firstPageData['view']['next']);
        $this->assertResponseIsSuccessful();
        $secondPageData = $secondPageResponse->toArray();

        $this->assertArrayHasKey('member', $secondPageData);
        $this->assertCount(5, $secondPageData['member']);

        // Store second page message IDs
        $secondPageMessageIds = array_column($secondPageData['member'], 'id');

        // Verify no duplicates between first and second page
        $duplicates = array_intersect($firstPageMessageIds, $secondPageMessageIds);
        $this->assertEmpty($duplicates, 'No messages should appear in both first and second page');

        // Verify second page messages are ordered correctly (newest first)
        $secondPageMessages = $secondPageData['member'];
        for ($i = 0; $i < \count($secondPageMessages) - 1; ++$i) {
            $this->assertGreaterThan(
                $secondPageMessages[$i + 1]['createdAt'],
                $secondPageMessages[$i]['createdAt'],
                'Second page messages should be ordered by creation date descending'
            );
        }

        // Verify second page messages are older than first page messages
        // (since we're ordering DESC, second page should have older timestamps)
        $lastFirstPageMessage = end($firstPageMessages);
        $firstSecondPageMessage = $secondPageMessages[0];
        $this->assertGreaterThan(
            $firstSecondPageMessage['createdAt'],
            $lastFirstPageMessage['createdAt'],
            'Second page should contain older messages than first page'
        );

        // Get third page to verify we can continue pagination
        $this->assertArrayHasKey('view', $secondPageData);
        $this->assertArrayHasKey('next', $secondPageData['view']);

        $thirdPageResponse = $client->request('GET', $secondPageData['view']['next']);
        $this->assertResponseIsSuccessful();
        $thirdPageData = $thirdPageResponse->toArray();

        $this->assertArrayHasKey('member', $thirdPageData);
        $this->assertCount(5, $thirdPageData['member']);

        // Store third page message IDs
        $thirdPageMessageIds = array_column($thirdPageData['member'], 'id');

        // Verify no duplicates across all three pages
        $allMessageIds = array_merge($firstPageMessageIds, $secondPageMessageIds, $thirdPageMessageIds);
        $uniqueMessageIds = array_unique($allMessageIds);
        $this->assertCount(\count($allMessageIds), $uniqueMessageIds, 'All messages across pages should be unique');

        // Verify we have all 15 messages across the three pages
        $this->assertCount(15, $uniqueMessageIds, 'All 15 messages should be returned across pages');
    }

    public function testGetConversationMessagesWithCreatedAtFilter(): void
    {
        $conversationOwner = UserFactory::new()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($conversationOwner)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        // Create messages with specific dates
        MessageFactory::new()->createMany(
            3,
            static function (int $i) use ($conversation) {
                return [
                    'role' => MessageRole::User,
                    'textContent' => \sprintf('Message %d', $i),
                    'conversation' => $conversation,
                    'createdAt' => new \DateTimeImmutable(\sprintf('-%d days', $i)),
                ];
            }
        );

        // Create a message from 5 days ago
        MessageFactory::new()->create([
            'role' => MessageRole::User,
            'textContent' => 'Message from 5 days ago',
            'conversation' => $conversation,
            'createdAt' => new \DateTimeImmutable('-5 days'),
        ]);

        // Create a message from 10 days ago
        MessageFactory::new()->create([
            'role' => MessageRole::User,
            'textContent' => 'Message from 10 days ago',
            'conversation' => $conversation,
            'createdAt' => new \DateTimeImmutable('-10 days'),
        ]);

        $client = $this->createAuthenticatedClient($conversationOwner);

        // Store conversation ID to avoid issues after requests
        $conversationId = $conversation->getId();

        // Test filtering messages created in the last 7 days
        $sevenDaysAgo = new \DateTimeImmutable('-7 days')
->format('Y-m-d');
        $response = $client->request(
            'GET',
            '/api/conversations/' . $conversationId . '/messages?createdAt[after]=' . $sevenDaysAgo
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);

        // Should return 4 messages (3 recent + 1 from 5 days ago, but not the one from 10 days ago)
        $this->assertCount(4, $responseData['member']);

        // Test filtering with both lower and upper bounds
        $sixDaysAgo = new \DateTimeImmutable('-6 days')
->format('Y-m-d');
        $fourDaysAgo = new \DateTimeImmutable('-4 days')
->format('Y-m-d');
        $response = $client->request(
            'GET',
            '/api/conversations/' . $conversationId . '/messages?createdAt[after]=' . $sixDaysAgo . '&createdAt[before]=' . $fourDaysAgo
        );

        $this->assertResponseIsSuccessful();

        $responseData = $response->toArray();
        $this->assertArrayHasKey('member', $responseData);

        // Should return 1 message (the one from 5 days ago)
        $this->assertCount(1, $responseData['member']);

        // Check the message structure - text content is in the contents array
        $message = $responseData['member'][0];
        $this->assertArrayHasKey('contents', $message);
        $this->assertCount(1, $message['contents']);
        $this->assertEquals('Message from 5 days ago', $message['contents'][0]['content']);
    }

    /**
     * Helper method to create a conversation with multiple messages.
     *
     * @param int         $messageCount  Number of messages to create
     * @param MessageRole $role          Role for all messages (default: User)
     * @param string      $messagePrefix Prefix for message content (default: 'Test message')
     * @param User        $owner         User who owns the watchfile
     */
    private function createConversationWithMessages(
        int $messageCount,
        User $owner,
        MessageRole $role = MessageRole::User,
        string $messagePrefix = 'Test message',
    ): Conversation {
        // Create a watchfile with the specified owner
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        MessageFactory::new()->createMany(
            $messageCount,
            static function (int $i) use ($role, $messagePrefix, $conversation) {
                return [
                    'role' => $role,
                    'textContent' => \sprintf('%s %d', $messagePrefix, $i),
                    'conversation' => $conversation,
                    'createdAt' => new \DateTimeImmutable(\sprintf('-%d days', $i)),
                ];
            }
        );

        return $conversation;
    }
}
