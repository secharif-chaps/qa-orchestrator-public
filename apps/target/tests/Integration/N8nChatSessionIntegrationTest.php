<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\AI\N8nChatSession;
use App\Tests\Utils\EntityUtilsTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class N8nChatSessionIntegrationTest extends AbstractApiTestCase
{
    use EntityUtilsTrait;

    public function testConversationSerializationWithLlmGroups(): void
    {
        // Arrange
        $watchFile = new WatchFile('Test Watchfile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'test-watch-file-id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'test-conversation-id');
        $conversation->setTitle('Test Conversation Title');

        $message = new Message();
        $this->forcePropertyValue($message, 'test-message-id');
        $message->setTextContent('Test message');
        $conversation->addMessage($message);

        $normalizer = $this->getContainer()
->get(NormalizerInterface::class);

        // Act
        $normalizedConversation = $normalizer->normalize($conversation, 'json', [
            'groups' => ['conversation:read', 'conversation:llm'],
        ]);

        $normalizedMessage = $normalizer->normalize($message, 'json', [
            'groups' => ['message:read', 'message:llm'],
        ]);

        // Assert
        $this->assertIsArray($normalizedConversation);
        $this->assertArrayHasKey('id', $normalizedConversation);
        $this->assertArrayHasKey('title', $normalizedConversation);
        $this->assertArrayHasKey('watchFile', $normalizedConversation);
        $this->assertArrayHasKey('createdAt', $normalizedConversation);
        $this->assertArrayHasKey('updatedAt', $normalizedConversation);

        $this->assertIsArray($normalizedMessage);
        $this->assertArrayHasKey('id', $normalizedMessage);
        $this->assertArrayHasKey('role', $normalizedMessage);
        $this->assertArrayHasKey('contents', $normalizedMessage);
        $this->assertArrayHasKey('createdAt', $normalizedMessage);
    }

    public function testN8nChatSessionDataStructure(): void
    {
        // Arrange
        $watchFile = new WatchFile('Test Watchfile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'test-watch-file-id');

        $conversation = new Conversation($watchFile);
        $this->forcePropertyValue($conversation, 'test-conversation-id');
        $conversation->setTitle('Test Conversation Title');

        $message = new Message();
        $this->forcePropertyValue($message, 'test-message-id');
        $message->setTextContent('Test message');
        $conversation->addMessage($message);

        $chatSession = $this->getContainer()
->get(N8nChatSession::class);
        $normalizer = $this->getContainer()
->get(NormalizerInterface::class);

        // Act - Simuler la création de l'objet $data comme dans N8nChatSession
        $data = [
            'watch_file' => $normalizer->normalize($conversation->getWatchFile(), 'json', [
                'groups' => ['watch_file:read', 'watch_file:llm'],
            ]),
            'message' => $normalizer->normalize($message, 'json', [
                'groups' => ['message:read', 'message:llm'],
            ]),
            'conversation' => $normalizer->normalize($conversation, 'json', [
                'groups' => ['conversation:read', 'conversation:llm'],
            ]),
            'conversation_id' => $conversation->getId(),
        ];

        // Assert
        $this->assertArrayHasKey('watch_file', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('conversation', $data);
        $this->assertArrayHasKey('conversation_id', $data);

        $this->assertIsString($data['conversation_id']);

        // Cast pour PHPStan
        $watchFile = (array) $data['watch_file'];
        $message = (array) $data['message'];
        $conversation = (array) $data['conversation'];
        $this->assertArrayHasKey('id', $watchFile);
        $this->assertArrayHasKey('id', $message);
        $this->assertArrayHasKey('id', $conversation);

        // Vérifier que l'objet conversation contient les bonnes propriétés
        $this->assertArrayHasKey('title', $conversation);
        $this->assertArrayHasKey('watchFile', $conversation);
        $this->assertArrayHasKey('createdAt', $conversation);
        $this->assertArrayHasKey('updatedAt', $conversation);
    }
}
