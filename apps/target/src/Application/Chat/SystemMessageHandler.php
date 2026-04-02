<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Domain\AI\LlmOutputSanitizerInterface;
use App\Domain\Chat\ConversationGatewayInterface;
use App\Domain\Chat\ConversationState;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Chat\MessageRole;
use App\Domain\Chat\MessageStatus;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class SystemMessageHandler
{
    private const string LOCK_PREFIX = 'system_message_lock';
    private const int DEFAULT_LOCK_TTL = 10; // 10 seconds

    public function __construct(
        private ConversationGatewayInterface $conversationGateway,
        private MessageGatewayInterface $messageGateway,
        private CacheItemPoolInterface $cache,
        private RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
        private LlmOutputSanitizerInterface $llmOutputSanitizer,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(SystemMessageAction $action): void
    {
        $conversation = $this->conversationGateway->get($action->conversationId);

        if (!$action->isValidMessage()) {
            $this->logger?->warning('Unable to retrieve system message from action');

            return;
        }

        // Use distributed lock to prevent duplicate messages
        $deduplicationKey = $action->context['deduplication_key'] ?? null;
        if (null !== $deduplicationKey && \is_string($deduplicationKey)) {
            if (!$this->acquireLock($deduplicationKey, self::DEFAULT_LOCK_TTL)) {
                $this->logger?->info('Duplicate system message detected (lock already exists), skipping', [
                    'conversation_id' => $action->conversationId,
                    'deduplication_key' => $deduplicationKey,
                ]);

                return;
            }
        }

        $sanitizedContent = $this->llmOutputSanitizer->sanitize($action->getTrimmedMessage());

        // Create system message with delivered status
        $message = new Message();
        $message->setTextContent($sanitizedContent);
        $message->setRole(MessageRole::System);
        $message->setConversation($conversation);
        $message->setStatus(MessageStatus::Delivered);
        $message->setMetadata($action->context);

        // System messages can indicate agent is processing (not idle yet)
        // Only set to AgentProcessing if currently waiting
        if ($conversation->getState()->isWaitingForAgent()) {
            $conversation->setState(ConversationState::AgentProcessing);
            $this->conversationGateway->save($conversation);
            $this->realTimeUpdatePublisher->publishConversationUpdate($conversation);
        }

        $this->messageGateway->save($message);
        $this->realTimeUpdatePublisher->publishMessageUpdate($message);

        $this->logger?->info('System message added to conversation', [
            'conversation_id' => $conversation->getId(),
            'message_id' => $message->getId(),
        ]);
    }

    /**
     * Acquire a distributed lock for a system message.
     * Returns true if lock was acquired, false if lock already exists.
     */
    private function acquireLock(string $deduplicationKey, int $ttlSeconds): bool
    {
        $lockKey = self::LOCK_PREFIX . ':' . $deduplicationKey;

        try {
            $cacheItem = $this->cache->getItem($lockKey);

            // If lock already exists, return false
            if ($cacheItem->isHit()) {
                $this->logger?->debug('System message lock already exists', [
                    'deduplication_key' => $deduplicationKey,
                    'lock_key' => $lockKey,
                ]);

                return false;
            }

            // Acquire lock
            $cacheItem->set('locked');
            $cacheItem->expiresAfter($ttlSeconds);
            $this->cache->save($cacheItem);

            $this->logger?->debug('System message lock acquired', [
                'deduplication_key' => $deduplicationKey,
                'lock_key' => $lockKey,
                'ttl' => $ttlSeconds,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger?->error('Failed to acquire system message lock', [
                'deduplication_key' => $deduplicationKey,
                'lock_key' => $lockKey,
                'error' => $e->getMessage(),
            ]);

            // On error, allow message to proceed (fail open)
            return true;
        }
    }
}
