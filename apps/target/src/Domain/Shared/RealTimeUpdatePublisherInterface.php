<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\WatchFile\WatchFile;

/**
 * Interface for publishing real-time updates to subscribed users.
 *
 * This interface abstracts the underlying real-time transport mechanism (e.g., Mercure, WebSockets)
 * and provides a technology-agnostic way to push updates to users.
 *
 * Updates are published to user-scoped topics, allowing each authorized user to receive
 * updates on their own subscription channel.
 *
 * Note: Updates are only published when the WatchFile is in DRAFT status (active for editing).
 */
interface RealTimeUpdatePublisherInterface
{
    /**
     * Publishes a WatchFile update to all authorized users.
     *
     * The implementation should resolve all users who have access to the WatchFile
     * (owner + all shared users with edit permissions). Each user receives the update
     * on their own user-scoped topic.
     *
     * Updates are only published when the WatchFile status is DRAFT.
     *
     * @param WatchFile $watchFile The WatchFile that was updated
     */
    public function publishWatchFileUpdate(WatchFile $watchFile): void;

    /**
     * Publishes a Conversation update to all users who have access to the conversation.
     *
     * The implementation should resolve all users with edit access to the WatchFile
     * associated with the conversation.
     *
     * Updates are only published when the WatchFile status is DRAFT.
     *
     * @param Conversation $conversation The Conversation that was updated
     */
    public function publishConversationUpdate(Conversation $conversation): void;

    /**
     * Publishes a Message update to all users who have access to the conversation.
     *
     * The implementation should resolve the conversation owner and any shared users
     * from the message's conversation and WatchFile relationships.
     *
     * Updates are only published when the WatchFile status is DRAFT.
     *
     * @param Message $message The Message that was created or updated
     */
    public function publishMessageUpdate(Message $message): void;
}
