<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Chat;

use App\Domain\Chat\Message;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Chat\MessageNotFoundException;
use App\Domain\Chat\MessageStatus;
use App\Tests\Utils\EntityUtilsTrait;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

class NullMessageGateway implements MessageGatewayInterface
{
    use EntityUtilsTrait;

    /**
     * @var array<string, Message>
     */
    private array $messages = [];

    public function get(string $id): Message
    {
        if (!isset($this->messages[$id])) {
            throw new MessageNotFoundException($id);
        }

        return $this->messages[$id];
    }

    public function save(Message $message): void
    {
        // Set an ID if it's null (for testing purposes)
        if (null === $message->getId()) {
            $this->forcePropertyValue($message, Uuid::v4()->toRfc4122());
        }

        $this->messages[$message->getId()] = $message;
    }

    public function addMessage(Message $message): void
    {
        if (null === $message->getId()) {
            $this->forcePropertyValue($message, Uuid::v4()->toRfc4122());
        }

        $this->messages[$message->getId()] = $message;
    }

    public function hasMessage(string $id): bool
    {
        return isset($this->messages[$id]);
    }

    /**
     * @return Message[]
     */
    public function getAll(): array
    {
        return array_values($this->messages);
    }

    public function findRecentByConversation(
        string $conversationId,
        array $ignoreMessageIds = [],
        int $limit = 20,
    ): array {
        $conversationMessages = array_filter(
            $this->messages,
            static fn (Message $message) => !\in_array($message->getId(), $ignoreMessageIds, true)
                && $message->getConversation()?->getId() === $conversationId,
        );

        // Sort by createdAt DESC and take the last $limit
        usort($conversationMessages, static fn (Message $a, Message $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

        return \array_slice($conversationMessages, -$limit);
    }

    public function applyEagerLoading(QueryBuilder $queryBuilder): void
    {
        // No-op for NullGateway - eager loading is only relevant for Doctrine
    }

    public function findByConversationAndStatus(string $conversationId, MessageStatus $status): array
    {
        return array_values(array_filter(
            $this->messages,
            static fn (Message $message) => $message->getConversation()?->getId() === $conversationId
                && $message->getStatus() === $status,
        ));
    }
}
