<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\Chat;

use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Chat\Conversation;
use App\Domain\Chat\ConversationState;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Conversation>
 */
class ConversationFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Conversation::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @return array{
     *     title: string,
     *     language: string,
     *     metadata: array<string, mixed>|null,
     * }
     */
    protected function defaults(): array
    {
        /** @var string $language */
        $language = self::faker()->randomElement(['en', 'fr']);

        return [
            'watchFile' => WatchFileFactory::new(),
            'title' => self::faker()->sentence(),
            'language' => $language,
            'metadata' => null,
            'state' => ConversationState::WaitingForAgent,
        ];
    }

    public function withCreatedAt(\DateTimeImmutable $createdAt): self
    {
        return $this->afterInstantiate(function (Conversation $conversation) use ($createdAt): void {
            $reflection = new \ReflectionClass($conversation);
            $property = $reflection->getProperty('createdAt');
            $property->setValue($conversation, $createdAt);
        });
    }
}
