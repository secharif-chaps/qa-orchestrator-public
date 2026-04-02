<?php

declare(strict_types=1);

namespace App\Domain\Chat;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

interface MessageGatewayInterface
{
    /**
     * Retrieves a message by its ID.
     */
    public function get(string $id): Message;

    public function save(Message $message): void;

    /**
     * Finds recent messages for a conversation with contents pre-loaded.
     *
     * @param list<Uuid|string> $ignoreMessageIds Message IDs to ignore
     *
     * @return list<Message>
     */
    public function findRecentByConversation(
        string $conversationId,
        array $ignoreMessageIds = [],
        int $limit = 20,
    ): array;

    /**
     * Applies eager loading joins to a query builder to prevent N+1 queries.
     *
     * This modifies the query builder to add LEFT JOINs for:
     * - Message contents (MessageContent relation)
     * - Message creator (User relation)
     */
    public function applyEagerLoading(QueryBuilder $queryBuilder): void;

    /**
     * Finds messages for a conversation with a specific status.
     *
     * @return list<Message>
     */
    public function findByConversationAndStatus(string $conversationId, MessageStatus $status): array;
}
