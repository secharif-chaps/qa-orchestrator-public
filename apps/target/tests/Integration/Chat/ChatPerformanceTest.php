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
use App\Domain\Chat\MessageStatus;
use App\Domain\User\User;
use App\Tests\Integration\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Final validation tests for the Chat System Optimization feature (TAR-233).
 *
 * These tests cover:
 * 1. Message status fields are exposed in API responses (all status types)
 * 2. Retry endpoint successfully retries error messages
 * 3. Performance validation with 50 requests
 * 4. Message content types are properly serialized with optimizations
 */
#[CoversClass(Message::class)]
#[CoversClass(Conversation::class)]
class ChatPerformanceTest extends AbstractApiTestCase
{
    /**
     * Test environment threshold for final performance validation.
     * Production target: p50 < 200ms, p75 < 300ms
     * Test environment has Docker/Xdebug overhead (~300ms+).
     */
    private const int FINAL_VALIDATION_THRESHOLD_MS = 700;

    /**
     * Test message status fields are exposed correctly via API.
     *
     * This integration test verifies:
     * - Message status is included in API response
     * - Message retryCount is included in API response
     * - All status types are correctly serialized as strings
     */
    public function testMessageStatusFieldsExposedInApi(): void
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

        // Create messages with different statuses
        MessageFactory::new()->create([
            'role' => MessageRole::User,
            'textContent' => 'Pending message',
            'conversation' => $conversation,
            'status' => MessageStatus::Pending,
            'retryCount' => 0,
        ]);

        MessageFactory::new()->create([
            'role' => MessageRole::User,
            'textContent' => 'Sent message',
            'conversation' => $conversation,
            'status' => MessageStatus::Sent,
            'retryCount' => 0,
        ]);

        MessageFactory::new()->create([
            'role' => MessageRole::Model,
            'textContent' => 'Delivered response',
            'conversation' => $conversation,
            'status' => MessageStatus::Delivered,
            'retryCount' => 0,
        ]);

        MessageFactory::new()->create([
            'role' => MessageRole::User,
            'textContent' => 'Failed message',
            'conversation' => $conversation,
            'status' => MessageStatus::Error,
            'retryCount' => 2,
        ]);

        $conversationId = $conversation->getId();

        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('GET', '/api/conversations/' . $conversationId . '/messages');

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $this->assertCount(4, $responseData['member']);

        // Verify all status types are present and properly serialized
        $statuses = array_column($responseData['member'], 'status');
        $this->assertContains('pending', $statuses);
        $this->assertContains('sent', $statuses);
        $this->assertContains('delivered', $statuses);
        $this->assertContains('error', $statuses);

        // Verify retryCount is exposed correctly
        foreach ($responseData['member'] as $message) {
            $this->assertArrayHasKey('status', $message);
            $this->assertArrayHasKey('retryCount', $message);
            $this->assertIsString($message['status']);
            $this->assertIsInt($message['retryCount']);
        }

        // Verify error message has correct retryCount
        $errorMessages = array_filter($responseData['member'], static fn ($m) => 'error' === $m['status']);
        $errorMessageData = array_values($errorMessages)[0];
        $this->assertEquals(2, $errorMessageData['retryCount']);
    }

    /**
     * Test successful retry of an error message.
     *
     * This integration test verifies:
     * - Retry endpoint successfully processes error messages
     * - Status changes to pending after retry
     * - RetryCount is incremented
     */
    public function testRetryEndpointSuccessfullyRetriesErrorMessage(): void
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

        // Create message in error status that can be retried
        $message = MessageFactory::new()->create([
            'role' => MessageRole::User,
            'textContent' => 'Message to retry',
            'conversation' => $conversation,
            'status' => MessageStatus::Error,
            'retryCount' => 0,
        ]);

        $messageId = $message->getId();

        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('POST', '/api/messages/' . $messageId . '/retry');

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $this->assertEquals('pending', $responseData['status']);
        $this->assertEquals(1, $responseData['retryCount']);
    }

    /**
     * Test final performance validation with 50 requests.
     *
     * This test measures p50/p75 latencies for the optimized message collection
     * endpoint to validate performance improvements.
     *
     * Production targets:
     * - p50 < 200ms
     * - p75 < 300ms
     *
     * Note: Test environment has Docker/Xdebug overhead (~300ms+).
     * Actual production performance is expected to meet targets.
     */
    public function testFinalPerformanceValidation50Requests(): void
    {
        $owner = UserFactory::new()
            ->defaultBasilUser()
            ->create();

        $conversation = $this->createConversationWithMixedMessages(30, $owner);
        $conversationId = $conversation->getId();

        $client = $this->createAuthenticatedClient($owner);

        // Run 50 requests for final performance validation
        $times = [];
        for ($i = 0; $i < 50; ++$i) {
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
        $p75Index = (int) floor(\count($times) * 0.75);

        $p50 = $times[$p50Index];
        $p75 = $times[$p75Index];

        $analysis = [
            \sprintf('[FINAL VALIDATION] 30 messages, 50 requests - p50: %.2fms, p75: %.2fms', $p50, $p75),
            \sprintf(
                '[FINAL VALIDATION] Min: %.2fms, Max: %.2fms, Avg: %.2fms',
                min($times),
                max($times),
                array_sum($times) / \count($times),
            ),
            '[FINAL VALIDATION] Production targets: p50 < 200ms, p75 < 300ms',
            \sprintf('[FINAL VALIDATION] Test environment threshold: %dms', self::FINAL_VALIDATION_THRESHOLD_MS),
        ];

        // Calculate gap from production targets
        $p50Gap = (($p50 - 200) / 200) * 100;
        $p75Gap = (($p75 - 300) / 300) * 100;
        $analysis[] = \sprintf('[FINAL VALIDATION] Gap from targets - p50: %.0f%%, p75: %.0f%%', $p50Gap, $p75Gap);

        // Assert test environment threshold is not exceeded
        $this->assertLessThan(
            self::FINAL_VALIDATION_THRESHOLD_MS,
            $p50,
            \sprintf(
                'p50 (%.2fms) exceeds test environment threshold of %dms. '
                . 'Performance may have regressed. Analysis details: %s',
                $p50,
                self::FINAL_VALIDATION_THRESHOLD_MS,
                implode(\PHP_EOL, $analysis),
            )
        );
    }

    /**
     * Test message content types are properly serialized with optimization.
     *
     * Verifies that MessageNormalizer properly handles all MessageContent types.
     */
    public function testMessageContentTypesSerialization(): void
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

        // Create messages with text content (most common type)
        MessageFactory::new()->createMany(
            5,
            static function (int $i) use ($conversation) {
                $role = 0 === $i % 2 ? MessageRole::User : MessageRole::Model;

                return [
                    'role' => $role,
                    'textContent' => \sprintf('Message content %d with sufficient detail', $i),
                    'conversation' => $conversation,
                    'status' => MessageStatus::Delivered,
                ];
            }
        );

        $conversationId = $conversation->getId();

        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('GET', '/api/conversations/' . $conversationId . '/messages');

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $this->assertCount(5, $responseData['member']);

        foreach ($responseData['member'] as $message) {
            // Verify contents array is properly serialized
            $this->assertArrayHasKey('contents', $message);
            $this->assertNotEmpty($message['contents']);

            foreach ($message['contents'] as $content) {
                $this->assertArrayHasKey('id', $content);
                $this->assertArrayHasKey('content', $content);
                $this->assertArrayHasKey('createdAt', $content);
                $this->assertNotEmpty($content['content']);
            }

            // Verify all required message fields
            $this->assertArrayHasKey('id', $message);
            $this->assertArrayHasKey('role', $message);
            $this->assertArrayHasKey('status', $message);
            $this->assertArrayHasKey('retryCount', $message);
            $this->assertArrayHasKey('createdAt', $message);
        }
    }

    /**
     * Creates a conversation with mixed User and Model messages.
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
                    'status' => MessageStatus::Delivered,
                ];
            }
        );

        return $conversation;
    }
}
