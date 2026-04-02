<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Shared\RealTimeTopicGeneratorInterface;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUserGatewayInterface;
use App\Infrastructure\Shared\EntityEnrichmentOrchestrator;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Publishes real-time updates via Mercure hub using user-scoped topics.
 *
 * This service implements the scalable topic pattern from ADR-2025-001, publishing
 * updates to user-specific topics rather than resource-specific topics. This ensures
 * that all authorized users receive updates on their own subscription channel.
 *
 * Updates are only published when the WatchFile is in DRAFT status to avoid
 * sending updates during automated processing or when archived.
 *
 * A debounce mechanism prevents publishing identical updates more than once
 * within a configurable time window (default: 30 seconds).
 */
class MercureUpdatePublisher implements RealTimeUpdatePublisherInterface
{
    private const int DEBOUNCE_TTL_SECONDS = 30;

    public function __construct(
        private readonly HubInterface $hub,
        private readonly RealTimeTopicGeneratorInterface $topicGenerator,
        private readonly WatchFileUserGatewayInterface $watchFileUserGateway,
        private readonly SerializerInterface $serializer,
        private readonly EntityEnrichmentOrchestrator $entityEnricher,
        private readonly ?CacheItemPoolInterface $mercureDebounceCache = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function publishWatchFileUpdate(WatchFile $watchFile): void
    {
        if (!$this->shouldPublishForWatchFile($watchFile)) {
            return;
        }

        $authorizedUsers = $this->watchFileUserGateway->getUsersWithRealTimeAccess($watchFile);

        if (0 === \count($authorizedUsers)) {
            $this->logger?->warning("No authorized users found for WatchFile {$watchFile->getId()}");

            return;
        }

        foreach ($authorizedUsers as $user) {
            $topic = $this->topicGenerator->forWatchFile($user, $watchFile);

            $enrichedWatchFile = $this->entityEnricher->enrich($watchFile, [
                'user' => $user,
            ]);
            $serializedData = $this->serializer->serialize($enrichedWatchFile, 'json', [
                'groups' => ['watch_file:read'],
            ]);

            $wasPublished = $this->publishWithDebounce($topic, $serializedData);

            $this->logger?->debug($wasPublished ? 'Published WatchFile update' : 'Debounced WatchFile update', [
                'watchfile_id' => $watchFile->getId(),
                'user_id' => $user->getId(),
                'topic' => $topic,
            ]);
        }
    }

    public function publishConversationUpdate(Conversation $conversation): void
    {
        $watchFile = $conversation->getWatchFile();

        if (!$this->shouldPublishForWatchFile($watchFile)) {
            return;
        }

        $authorizedUsers = $this->watchFileUserGateway->getUsersWithRealTimeAccess($watchFile);

        if (0 === \count($authorizedUsers)) {
            $this->logger?->warning("No authorized users found for conversation {$conversation->getId()}");

            return;
        }

        $serializedData = $this->serializer->serialize($conversation, 'json', [
            'groups' => ['conversation:read'],
        ]);

        foreach ($authorizedUsers as $user) {
            $topic = $this->topicGenerator->forConversation($user, $conversation);

            $wasPublished = $this->publishWithDebounce($topic, $serializedData);

            $this->logger?->debug($wasPublished ? 'Published Conversation update' : 'Debounced Conversation update', [
                'conversation_id' => $conversation->getId(),
                'user_id' => $user->getId(),
                'topic' => $topic,
            ]);
        }
    }

    public function publishMessageUpdate(Message $message): void
    {
        $conversation = $message->getConversation();

        if (null === $conversation) {
            $this->logger?->warning("Message {$message->getId()} has no conversation attached.");

            return;
        }

        $watchFile = $conversation->getWatchFile();

        if (!$this->shouldPublishForWatchFile($watchFile)) {
            return;
        }

        $authorizedUsers = $this->watchFileUserGateway->getUsersWithRealTimeAccess($watchFile);

        if (0 === \count($authorizedUsers)) {
            $this->logger?->warning("No authorized users found for conversation {$conversation->getId()}");

            return;
        }

        $serializedData = $this->serializer->serialize($message, 'json', [
            'groups' => ['message:read'],
        ]);

        foreach ($authorizedUsers as $user) {
            $topic = $this->topicGenerator->forConversationMessages($user, $conversation);

            $wasPublished = $this->publishWithDebounce($topic, $serializedData);

            $this->logger?->debug($wasPublished ? 'Published Message update' : 'Debounced Message update', [
                'message_id' => $message->getId(),
                'conversation_id' => $conversation->getId(),
                'user_id' => $user->getId(),
                'topic' => $topic,
            ]);
        }
    }

    /**
     * Determines if real-time updates should be published for the given WatchFile.
     *
     * Updates are only published when the WatchFile is in DRAFT status,
     * which indicates the WatchFile is being actively edited by users.
     */
    private function shouldPublishForWatchFile(WatchFile $watchFile): bool
    {
        if (WatchFileStatus::DRAFT !== $watchFile->getStatus()) {
            $this->logger?->debug('Skipping real-time update for non-draft WatchFile', [
                'watchfile_id' => $watchFile->getId(),
                'status' => $watchFile
                    ->getStatus()
                    ->value,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Publishes an update if it hasn't been recently published (debounce).
     *
     * Returns true if the update was published, false if it was debounced.
     */
    private function publishWithDebounce(string $topic, string $data): bool
    {
        if (null === $this->mercureDebounceCache) {
            $this->hub->publish(new Update($topic, $data, true));

            return true;
        }

        $cacheKey = 'mercure_debounce_' . hash('xxh3', $topic . $data);

        $cacheItem = $this->mercureDebounceCache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return false;
        }

        $cacheItem->set(true);
        $cacheItem->expiresAfter(self::DEBOUNCE_TTL_SECONDS);
        $this->mercureDebounceCache->save($cacheItem);

        $this->hub->publish(new Update($topic, $data, true));

        return true;
    }
}
