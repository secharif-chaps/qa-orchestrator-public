<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure\Test;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Update;

/**
 * @internal should use only in tests
 *
 * A null/spy implementation of HubInterface for testing purposes.
 * This hub avoids network calls and tracks all published updates for assertions.
 */
class NullHub implements HubInterface
{
    private NullTokenProvider $tokenProvider;

    /**
     * @var list<Update>
     */
    private array $publishedUpdates = [];

    public function __construct()
    {
        $this->tokenProvider = new NullTokenProvider();
    }

    public function getUrl(): string
    {
        return 'http://null/.well-known/mercure';
    }

    public function getPublicUrl(): string
    {
        return 'http://null/.well-known/mercure';
    }

    public function getProvider(): TokenProviderInterface
    {
        return $this->tokenProvider;
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return null;
    }

    public function publish(Update $update): string
    {
        $this->publishedUpdates[] = $update;

        return 'urn:uuid:null-' . bin2hex(random_bytes(16));
    }

    /**
     * Get count of published updates.
     */
    public function getPublishedCount(): int
    {
        return \count($this->publishedUpdates);
    }

    /**
     * Get all published updates.
     *
     * @return list<Update>
     */
    public function getPublishedUpdates(): array
    {
        return $this->publishedUpdates;
    }

    /**
     * Get published updates filtered by topic pattern.
     *
     * @return list<Update>
     */
    public function getPublishedUpdatesForTopic(string $topicPattern): array
    {
        return array_values(array_filter(
            $this->publishedUpdates,
            static function (Update $update) use ($topicPattern): bool {
                foreach ($update->getTopics() as $topic) {
                    if (str_contains($topic, $topicPattern)) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }

    /**
     * Reset spy state between tests.
     */
    public function reset(): void
    {
        $this->publishedUpdates = [];
    }
}
