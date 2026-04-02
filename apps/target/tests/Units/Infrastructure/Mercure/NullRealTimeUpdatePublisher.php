<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Mercure;

use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\WatchFile\WatchFile;

/**
 * Null implementation of RealTimeUpdatePublisherInterface for testing.
 *
 * This implementation does nothing when called, allowing tests to verify
 * other behaviors without side effects from real-time updates.
 */
class NullRealTimeUpdatePublisher implements RealTimeUpdatePublisherInterface
{
    public function publishWatchFileUpdate(WatchFile $watchFile): void
    {
        // Do nothing - this is a null implementation for testing
    }

    public function publishConversationUpdate(Conversation $conversation): void
    {
        // Do nothing - this is a null implementation for testing
    }

    public function publishMessageUpdate(Message $message): void
    {
        // Do nothing - this is a null implementation for testing
    }
}
