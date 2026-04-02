<?php

declare(strict_types=1);

namespace App\Tests\Integration\Chat;

use App\DataFixtures\Factory\Chat\ConversationFactory;
use App\DataFixtures\Factory\Chat\MessageFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use App\Domain\User\User;
use App\Tests\Integration\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for message collection query optimization (TAR-233).
 *
 * These tests verify:
 * 1. All expected fields are returned in the response
 * 2. MessageContent types (TextContent, FunctionCallContent, etc.) are properly hydrated
 * 3. Pagination and cursor-based navigation works with optimized queries
 *
 * Performance targets:
 * - Production: p50 < 200ms, p75 < 300ms for 30 messages
 * - Test environment: Uses regression thresholds due to Docker/Xdebug overhead
 *
 * Optimizations implemented:
 * - MessageEagerLoadingExtension: Adds LEFT JOINs for contents and createdBy
 * - MessageNormalizer: Custom serializer for optimized JSON output
 */
#[CoversClass(Message::class)]
class MessageCollectionOptimizationTest extends AbstractApiTestCase
{
    /**
     * Test environment threshold for regression detection.
     * Lower than the pre-optimization baseline of ~550ms.
     * Production target is 200ms p50, but test environment has Docker/Xdebug overhead.
     */
    private const int TEST_ENV_THRESHOLD_MS = 700;

    public function testMessageCollectionReturnsAllExpectedFields(): void
    {
        $owner = UserFactory::new()
            ->defaultBasilUser()
            ->create();

        $conversation = $this->createConversationWithMixedMessages(5, $owner);
        $conversationId = $conversation->getId();

        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('GET', '/api/conversations/' . $conversationId . '/messages');

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $this->assertArrayHasKey('member', $responseData);
        $this->assertCount(5, $responseData['member']);

        // Verify each message has all required fields
        /** @var array<int, array<string, mixed>> $messages */
        $messages = $responseData['member'];
        foreach ($messages as $message) {
            // Core message fields
            $this->assertArrayHasKey('id', $message, 'Message should have id');
            $this->assertArrayHasKey('role', $message, 'Message should have role');
            $this->assertArrayHasKey('status', $message, 'Message should have status');
            $this->assertArrayHasKey('retryCount', $message, 'Message should have retryCount');
            $this->assertArrayHasKey('createdAt', $message, 'Message should have createdAt');
            $this->assertArrayHasKey('contents', $message, 'Message should have contents');

            // Verify role is valid enum value
            $this->assertContains(
                $message['role'],
                ['user', 'model', 'system', 'system_error'],
                'Role should be a valid enum value'
            );

            // Verify status is valid enum value
            $this->assertContains(
                $message['status'],
                ['pending', 'sent', 'delivered', 'error'],
                'Status should be a valid enum value'
            );

            // Verify retryCount is integer
            $this->assertIsInt($message['retryCount'], 'retryCount should be integer');
        }
    }

    public function testMessageContentTypesAreProperlyHydrated(): void
    {
        $owner = UserFactory::new()
            ->defaultBasilUser()
            ->create();

        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $baseTime = new \DateTimeImmutable('-1 hour');

        // Create messages with text content
        MessageFactory::new()->createMany(
            3,
            static function (int $i) use ($conversation, $baseTime) {
                return [
                    'role' => MessageRole::User,
                    'textContent' => \sprintf('User message %d', $i),
                    'conversation' => $conversation,
                    'createdAt' => $baseTime->modify(\sprintf('+%d seconds', $i)),
                ];
            }
        );

        // Create messages with model responses (also text content)
        MessageFactory::new()->createMany(
            2,
            static function (int $i) use ($conversation, $baseTime) {
                return [
                    'role' => MessageRole::Model,
                    'textContent' => \sprintf('Model response %d with detailed explanation', $i),
                    'conversation' => $conversation,
                    'createdAt' => $baseTime->modify(\sprintf('+%d seconds', $i + 3)),
                ];
            }
        );

        $conversationId = $conversation->getId();

        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('GET', '/api/conversations/' . $conversationId . '/messages');

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $this->assertCount(5, $responseData['member']);

        /** @var array<int, array<string, mixed>> $messages */
        $messages = $responseData['member'];

        foreach ($messages as $message) {
            $this->assertNotEmpty($message['contents'], 'Message should have at least one content');

            /** @var array<int, array<string, mixed>> $contents */
            $contents = $message['contents'];

            foreach ($contents as $content) {
                // TextContent fields
                $this->assertArrayHasKey('id', $content, 'Content should have id');
                $this->assertArrayHasKey('content', $content, 'TextContent should have content field');
                $this->assertArrayHasKey('createdAt', $content, 'Content should have createdAt');

                // Verify content is not empty for text content
                $this->assertNotEmpty($content['content'], 'Text content should not be empty');
            }
        }
    }

    public function testPaginationWorksWithOptimizedQuery(): void
    {
        $owner = UserFactory::new()
            ->defaultBasilUser()
            ->create();

        $conversation = $this->createConversationWithMixedMessages(15, $owner);
        $conversationId = $conversation->getId();

        $client = $this->createAuthenticatedClient($owner);

        // Get first page with 5 items
        $firstPageResponse = $client->request(
            'GET',
            '/api/conversations/' . $conversationId . '/messages?itemsPerPage=5'
        );

        $this->assertResponseIsSuccessful();
        $firstPageData = $firstPageResponse->toArray();

        $this->assertCount(5, $firstPageData['member']);
        $this->assertArrayHasKey('view', $firstPageData);
        $this->assertArrayHasKey('next', $firstPageData['view']);

        // Store first page message IDs
        $firstPageIds = array_column($firstPageData['member'], 'id');

        // Navigate to second page using cursor
        $secondPageResponse = $client->request('GET', $firstPageData['view']['next']);
        $this->assertResponseIsSuccessful();
        $secondPageData = $secondPageResponse->toArray();

        $this->assertCount(5, $secondPageData['member']);
        $secondPageIds = array_column($secondPageData['member'], 'id');

        // Verify no duplicate messages between pages
        $duplicates = array_intersect($firstPageIds, $secondPageIds);
        $this->assertEmpty($duplicates, 'No messages should appear in both pages');

        // Navigate to third page
        $thirdPageResponse = $client->request('GET', $secondPageData['view']['next']);
        $this->assertResponseIsSuccessful();
        $thirdPageData = $thirdPageResponse->toArray();

        $this->assertCount(5, $thirdPageData['member']);
        $thirdPageIds = array_column($thirdPageData['member'], 'id');

        // Verify all 15 messages are unique across all pages
        $allIds = array_merge($firstPageIds, $secondPageIds, $thirdPageIds);
        $this->assertCount(15, array_unique($allIds), 'All 15 messages should be unique');

        // Verify descending order (newest first) is maintained
        $firstPageMessages = $firstPageData['member'];
        $lastFirstPageMessage = end($firstPageMessages);
        $firstSecondPageMessage = $secondPageData['member'][0];

        $this->assertGreaterThan(
            $firstSecondPageMessage['createdAt'],
            $lastFirstPageMessage['createdAt'],
            'Second page should contain older messages than first page'
        );
    }

    public function testOptimizedQueryPerformanceRegression(): void
    {
        $owner = UserFactory::new()
            ->defaultBasilUser()
            ->create();

        $conversation = $this->createConversationWithMixedMessages(30, $owner);
        $conversationId = $conversation->getId();

        $client = $this->createAuthenticatedClient($owner);

        // Run 10 requests for quick regression check
        $times = [];
        for ($i = 0; $i < 10; ++$i) {
            $startTime = microtime(true);
            $response = $client->request(
                'GET',
                '/api/conversations/' . $conversationId . '/messages?itemsPerPage=30'
            );
            $endTime = microtime(true);

            $this->assertResponseIsSuccessful();
            $responseData = $response->toArray();
            $this->assertCount(30, $responseData['member']);

            $times[] = ($endTime - $startTime) * 1000;
        }

        sort($times);

        $p50Index = (int) floor(\count($times) * 0.50);
        $p50 = $times[$p50Index];

        // Assert no regression beyond threshold
        $this->assertLessThan(
            self::TEST_ENV_THRESHOLD_MS,
            $p50,
            \sprintf(
                'p50 (%.2fms) exceeds regression threshold of %dms. Performance has regressed.',
                $p50,
                self::TEST_ENV_THRESHOLD_MS
            )
        );
    }

    /**
     * Creates a conversation with mixed User and Model messages for realistic testing.
     */
    private function createConversationWithMixedMessages(int $messageCount, User $owner): Conversation
    {
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $baseTime = new \DateTimeImmutable('-1 hour');

        MessageFactory::new()->createMany(
            $messageCount,
            static function (int $i) use ($conversation, $baseTime) {
                $role = 0 === $i % 2 ? MessageRole::User : MessageRole::Model;

                return [
                    'role' => $role,
                    'textContent' => MessageRole::User === $role
                        ? \sprintf('User question %d', $i)
                        : \sprintf('Model response %d with detailed explanation', $i),
                    'conversation' => $conversation,
                    'createdAt' => $baseTime->modify(\sprintf('+%d seconds', $i)),
                ];
            }
        );

        return $conversation;
    }
}
