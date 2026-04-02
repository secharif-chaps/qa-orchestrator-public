<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use App\Domain\Chat\Conversation;
use App\Domain\Shared\RealTimeTopicGeneratorInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;

/**
 * Generates Mercure topics for real-time updates using user-scoped format.
 *
 * Topic format: /users/{userId}/{resourceType}/{resourceId}
 *
 * This implementation follows ADR-2025-001 for scalable Mercure topics,
 * enabling constant token size regardless of the number of resources a user has access to.
 */
readonly class MercureTopicGenerator implements RealTimeTopicGeneratorInterface
{
    /**
     * Generates a topic string for a WatchFile update targeted at a specific user.
     *
     * @param User      $user      The user who should receive the update
     * @param WatchFile $watchFile The WatchFile being updated
     *
     * @return string The topic string in format `/users/{userId}/watch-files/{watchFileId}`
     */
    public function forWatchFile(User $user, WatchFile $watchFile): string
    {
        return \sprintf('/users/%s/watch-files/%s', $user->getId(), $watchFile->getId());
    }

    /**
     * Generates a topic string for a Conversation update targeted at a specific user.
     *
     * @param User         $user         The user who should receive the update
     * @param Conversation $conversation The Conversation being updated
     *
     * @return string The topic string in format `/users/{userId}/conversations/{conversationId}`
     */
    public function forConversation(User $user, Conversation $conversation): string
    {
        $conversationId = $conversation->getId();
        \assert(null !== $conversationId, 'Conversation must have an ID to generate a topic');

        return $this->forConversationById($user, $conversationId);
    }

    public function forWatchFileById(User $user, string $watchFileId): string
    {
        return \sprintf('/users/%s/watch-files/%s', $user->getId(), $watchFileId);
    }

    public function forConversationById(User $user, string $conversationId): string
    {
        return \sprintf('/users/%s/conversations/%s', $user->getId(), $conversationId);
    }

    public function forConversationMessages(User $user, Conversation $conversation): string
    {
        $conversationId = $conversation->getId();
        \assert(null !== $conversationId, 'Conversation must have an ID to generate a topic');

        return $this->forConversationMessagesById($user, $conversationId);
    }

    public function forConversationMessagesById(User $user, string $conversationId): string
    {
        return \sprintf('/users/%s/conversations/%s/messages', $user->getId(), $conversationId);
    }

    /**
     * Returns URI Templates for JWT subscription claims.
     *
     * These templates allow the user to subscribe to all their resources of each type
     * without requiring explicit enumeration in the token.
     *
     * @param User $user The user for whom to generate subscription templates
     *
     * @return list<string> Array of URI template strings
     */
    public function getSubscriptionTemplates(User $user): array
    {
        $userId = $user->getId();

        return [
            \sprintf('/users/%s/watch-files/{id}', $userId),
            \sprintf('/users/%s/conversations/{id}', $userId),
            \sprintf('/users/%s/conversations/{id}/messages', $userId),
        ];
    }
}
