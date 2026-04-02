<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\Chat;

use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use App\Domain\Chat\MessageStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Message>
 */
class MessageFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Message::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @return array{
     *     role: MessageRole,
     *     metadata: array<string, mixed>|null,
     *     status: MessageStatus,
     *     retryCount: int,
     * }
     */
    protected function defaults(): array
    {
        return [
            'role' => MessageRole::User,
            'metadata' => null,
            'conversation' => null,
            'status' => MessageStatus::Sent,
            'retryCount' => 0,
        ];
    }
}
