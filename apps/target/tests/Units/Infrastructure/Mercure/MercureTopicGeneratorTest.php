<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Mercure;

use App\Domain\Chat\Conversation;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeTopicGeneratorInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Mercure\MercureTopicGenerator;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MercureTopicGenerator::class)]
class MercureTopicGeneratorTest extends TestCase
{
    use EntityUtilsTrait;
    private MercureTopicGenerator $topicGenerator;

    protected function setUp(): void
    {
        $this->topicGenerator = new MercureTopicGenerator();
    }

    public function testImplementsRealTimeTopicGeneratorInterface(): void
    {
        $this->assertInstanceOf(RealTimeTopicGeneratorInterface::class, $this->topicGenerator);
    }

    public function testForWatchFileReturnsCorrectUserScopedTopicFormat(): void
    {
        // Arrange
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFileId = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

        $user = new User($userId, 'test@example.com');
        $watchFile = new WatchFile('Test Watch File', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $watchFileId);

        // Act
        $topic = $this->topicGenerator->forWatchFile($user, $watchFile);

        // Assert
        $expectedTopic = '/users/550e8400-e29b-41d4-a716-446655440000/watch-files/7c9e6679-7425-40de-944b-e07fc1f90ae7';
        $this->assertSame($expectedTopic, $topic);
    }

    public function testForConversationReturnsCorrectUserScopedTopicFormat(): void
    {
        // Arrange
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $conversationId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $user = new User($userId, 'test@example.com');
        $watchFile = new WatchFile('Test Watch File', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, $conversationId);

        // Act
        $topic = $this->topicGenerator->forConversation($user, $conversation);

        // Assert
        $expectedTopic = '/users/550e8400-e29b-41d4-a716-446655440000/conversations/a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $this->assertSame($expectedTopic, $topic);
    }

    public function testGetSubscriptionTemplatesReturnsCorrectUriTemplatesArray(): void
    {
        // Arrange
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User($userId, 'test@example.com');

        // Act
        $templates = $this->topicGenerator->getSubscriptionTemplates($user);

        // Assert
        $expectedTemplates = [
            '/users/550e8400-e29b-41d4-a716-446655440000/watch-files/{id}',
            '/users/550e8400-e29b-41d4-a716-446655440000/conversations/{id}',
            '/users/550e8400-e29b-41d4-a716-446655440000/conversations/{id}/messages',
        ];
        $this->assertSame($expectedTemplates, $templates);
    }

    public function testForConversationMessagesByIdReturnsCorrectTopicFormat(): void
    {
        // Arrange
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $conversationId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $user = new User($userId, 'test@example.com');

        // Act
        $topic = $this->topicGenerator->forConversationMessagesById($user, $conversationId);

        // Assert
        $expectedTopic = '/users/550e8400-e29b-41d4-a716-446655440000/conversations/a1b2c3d4-e5f6-7890-abcd-ef1234567890/messages';
        $this->assertSame($expectedTopic, $topic);
    }

    public function testTopicFormatsUseRfc4122UuidFormat(): void
    {
        // Arrange
        // UUID format: 8-4-4-4-12 hexadecimal characters with lowercase
        $userId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $watchFileId = '11111111-2222-3333-4444-555555555555';

        $user = new User($userId, 'test@example.com');
        $watchFile = new WatchFile('Test Watch File', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $watchFileId);

        // Act
        $topic = $this->topicGenerator->forWatchFile($user, $watchFile);

        // Assert
        // Verify the topic contains properly formatted UUIDs
        $this->assertMatchesRegularExpression(
            '#^/users/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/watch-files/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$#',
            $topic
        );
    }
}
