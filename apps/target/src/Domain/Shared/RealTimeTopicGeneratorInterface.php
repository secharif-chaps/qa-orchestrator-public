<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Chat\Conversation;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;

/**
 * Interface for generating real-time subscription topics.
 *
 * Topic Format:
 * Topics are user-scoped to enable efficient subscription management with constant token size.
 * The format is `/users/{userId}/{resourceType}/{resourceId}` where:
 * - `{userId}` is the UUID of the user who should receive updates
 * - `{resourceType}` is the type of resource (e.g., 'watch-files', 'conversations')
 * - `{resourceId}` is the UUID of the specific resource
 *
 * URI Templates:
 * For JWT subscription claims, URI templates are used to allow subscribing to all resources
 * of a given type without listing each one explicitly. Format: `/users/{userId}/{resourceType}/{id}`
 * where `{id}` is a placeholder that matches any resource ID.
 *
 * This design ensures tokens remain small and eliminates database queries during token generation.
 */
interface RealTimeTopicGeneratorInterface
{
    /**
     * Generates a topic string for a WatchFile update targeted at a specific user.
     *
     * @param User      $user      The user who should receive the update
     * @param WatchFile $watchFile The WatchFile being updated
     *
     * @return string The topic string in format `/users/{userId}/watch-files/{watchFileId}`
     */
    public function forWatchFile(User $user, WatchFile $watchFile): string;

    /**
     * Generates a topic string for a Conversation update targeted at a specific user.
     *
     * @param User         $user         The user who should receive the update
     * @param Conversation $conversation The Conversation being updated
     *
     * @return string The topic string in format `/users/{userId}/conversations/{conversationId}`
     */
    public function forConversation(User $user, Conversation $conversation): string;

    /**
     * Generates a topic string for a WatchFile by its ID.
     *
     * This is useful when only the WatchFile ID is available (e.g., from URL parsing).
     *
     * @param User   $user        The user who should receive the update
     * @param string $watchFileId The WatchFile UUID
     *
     * @return string The topic string in format `/users/{userId}/watch-files/{watchFileId}`
     */
    public function forWatchFileById(User $user, string $watchFileId): string;

    /**
     * Generates a topic string for a Conversation by its ID.
     *
     * This is useful when only the Conversation ID is available (e.g., from URL parsing).
     *
     * @param User   $user           The user who should receive the update
     * @param string $conversationId The Conversation UUID
     *
     * @return string The topic string in format `/users/{userId}/conversations/{conversationId}`
     */
    public function forConversationById(User $user, string $conversationId): string;

    /**
     * Generates a topic string for the messages collection of a Conversation.
     *
     * This topic is used for real-time updates when messages are added/modified/deleted
     * in a conversation. It is separate from the conversation topic to allow clients
     * to subscribe specifically to message changes.
     *
     * @param User         $user         The user who should receive the update
     * @param Conversation $conversation The Conversation whose messages are being updated
     *
     * @return string The topic string in format `/users/{userId}/conversations/{conversationId}/messages`
     */
    public function forConversationMessages(User $user, Conversation $conversation): string;

    /**
     * Generates a topic string for the messages collection of a Conversation by its ID.
     *
     * This is useful when only the Conversation ID is available (e.g., from URL parsing).
     *
     * @param User   $user           The user who should receive the update
     * @param string $conversationId The Conversation UUID
     *
     * @return string The topic string in format `/users/{userId}/conversations/{conversationId}/messages`
     */
    public function forConversationMessagesById(User $user, string $conversationId): string;

    /**
     * Returns URI Templates for JWT subscription claims.
     *
     * These templates allow the user to subscribe to all their resources of each type
     * without requiring explicit enumeration in the token.
     *
     * @param User $user The user for whom to generate subscription templates
     *
     * @return list<string> Array of URI template strings, e.g.:
     *                      [
     *                      '/users/{userId}/watch-files/{id}',
     *                      '/users/{userId}/conversations/{id}',
     *                      '/users/{userId}/conversations/{id}/messages'
     *                      ]
     */
    public function getSubscriptionTemplates(User $user): array;
}
