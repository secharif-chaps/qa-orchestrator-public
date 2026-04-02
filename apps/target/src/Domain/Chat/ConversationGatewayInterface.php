<?php

declare(strict_types=1);

namespace App\Domain\Chat;

use App\Domain\Agent\AgentExecution;

interface ConversationGatewayInterface
{
    public function get(string $id): Conversation;

    public function findByAgentExecution(AgentExecution $execution): ?Conversation;

    public function getLastConversationByWatchFileId(string $watchFileId): Conversation;

    public function save(Conversation $conversation): void;

    /**
     * @param string[] $watchFileIds
     *
     * @return string[] Conversation IDs
     */
    public function getLastConversationIdsForWatchFiles(array $watchFileIds): array;
}
