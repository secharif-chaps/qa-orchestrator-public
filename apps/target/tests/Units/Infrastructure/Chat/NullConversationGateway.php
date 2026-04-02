<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Chat;

use App\Domain\Agent\AgentExecution;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\ConversationGatewayInterface;
use App\Tests\Utils\EntityUtilsTrait;

class NullConversationGateway implements ConversationGatewayInterface
{
    use EntityUtilsTrait;
    public ?Conversation $savedConversation = null;

    /** @var Conversation[] */
    private array $conversations = [];

    public function get(string $id): Conversation
    {
        if (!isset($this->conversations[$id])) {
            throw new \Exception('Conversation not found');
        }

        return $this->conversations[$id];
    }

    public function save(Conversation $conversation): void
    {
        $this->savedConversation = $conversation;

        // Set an ID if it's null (for testing purposes)
        if (null === $conversation->getId()) {
            $this->forcePropertyValue($conversation, 'conv_id');
        }

        $this->conversations[$conversation->getId()] = $conversation;
    }

    public function addConversation(Conversation $conversation): void
    {
        if (null === $conversation->getId()) {
            $this->forcePropertyValue($conversation, 'conv_id');
        }

        $this->conversations[$conversation->getId()] = $conversation;
    }

    public function findByAgentExecution(AgentExecution $execution): ?Conversation
    {
        foreach ($this->conversations as $conversation) {
            if ($conversation->getAgentExecution() === $execution) {
                return $conversation;
            }
        }

        return null;
    }

    public function getLastConversationByWatchFileId(string $watchFileId): Conversation
    {
        foreach ($this->conversations as $conv) {
            if ($conv->getWatchFile()->getId() === $watchFileId) {
                return $conv;
            }
        }
        throw new \Exception('No conversation for watch file');
    }

    public function getLastConversationIdsForWatchFiles(array $watchFileIds): array
    {
        $result = [];

        foreach ($this->conversations as $conversation) {
            $watchFileId = $conversation->getWatchFile()
                ->getId();
            $conversationId = $conversation->getId();
            if (null === $conversationId) {
                continue;
            }
            if (\in_array($watchFileId, $watchFileIds, true)) {
                if (!isset($result[$watchFileId])
                    || $result[$watchFileId]['createdAt'] < $conversation->getCreatedAt()
                ) {
                    $result[$watchFileId] = [
                        'id' => $conversationId,
                        'createdAt' => $conversation->getCreatedAt(),
                    ];
                }
            }
        }

        /** @var array<string, array{id: string, createdAt: \DateTimeImmutable}> $result */
        return array_map(fn (array $item) => $item['id'], array_values($result));
    }
}
