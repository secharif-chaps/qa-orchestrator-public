<?php

declare(strict_types=1);

namespace App\Application\WatchFile\EventContext;

use App\Domain\Actor\ActorType;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Domain\WatchFileEvent\WatchFileEventGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AsMessageHandler]
class GetWatchFileEventsHandler
{
    private const string CACHE_KEY_PREFIX = 'watch_file_events_context_';
    private const int CACHE_TTL = 86400; // 24 hours

    public function __construct(
        private readonly WatchFileEventGatewayInterface $watchFileEventGateway,
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{events: array<int, WatchFileEvent>, actors: array<int, array{id: string, label: string, type: ActorType|null}>, referenceSubject: TranslatedText|null}
     */
    public function __invoke(GetWatchFileEventsAction $action): array
    {
        $cacheKey = $this->generateCacheKey($action->watchFileId, $action->days);

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($action) {
                $item->expiresAfter(self::CACHE_TTL);

                $this->logger->info('Building watchfile events context (cache miss)', [
                    'watch_file_id' => $action->watchFileId,
                    'days' => $action->days,
                ]);

                $context = $this->buildEventContext($action->watchFileId, $action->days);

                $this->logger->info('WatchFile events context built and cached', [
                    'watch_file_id' => $action->watchFileId,
                    'days' => $action->days,
                    'events_count' => \count($context['events']),
                    'actors_count' => \count($context['actors']),
                    'cache_ttl' => self::CACHE_TTL,
                ]);

                return $context;
            });
        } catch (\Exception $e) {
            $this->logger->error('Failed to get watchfile events context', [
                'watch_file_id' => $action->watchFileId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Invalidate cache for a specific watchfile.
     * Should be called when a new event is created for this WatchFile.
     */
    public function invalidateCache(string $watchFileId): void
    {
        try {
            $cacheKey = $this->generateCacheKey($watchFileId, GetWatchFileEventsAction::DEFAULT_DAYS);
            $this->cache->delete($cacheKey);

            $this->logger->info('WatchFile events context cache invalidated', [
                'watch_file_id' => $watchFileId,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to invalidate watchfile events context cache', [
                'watch_file_id' => $watchFileId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{events: array<int, WatchFileEvent>, actors: array<int, array{id: string, label: string, type: ActorType|null}>, referenceSubject: TranslatedText|null}
     */
    private function buildEventContext(string $watchFileId, int $days): array
    {
        $watchFile = $this->watchFileGateway->get($watchFileId);

        $events = $this->watchFileEventGateway->getRecentEvents($watchFileId, $days);

        $actors = [];
        foreach ($watchFile->getWatchFileActors() as $watchFileActor) {
            $actor = $watchFileActor->getActor();
            $actorId = $actor->getId();

            if (null === $actorId) {
                continue;
            }

            $actors[] = [
                'id' => $actorId,
                'label' => $actor->getLabel(),
                'type' => $watchFileActor->getType(),
            ];
        }

        return [
            'events' => $events,
            'actors' => $actors,
            'referenceSubject' => $watchFile->getReferenceSubject(),
        ];
    }

    private function generateCacheKey(string $watchFileId, int $days): string
    {
        return self::CACHE_KEY_PREFIX . $watchFileId . '_' . $days . 'd';
    }
}
