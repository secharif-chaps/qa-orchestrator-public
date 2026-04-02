<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\WatchFile\WatchFile;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Doctrine listener that automatically publishes Mercure updates
 * when WatchFile entities are updated.
 *
 * The listener tracks updated WatchFiles during the flush cycle and publishes
 * Mercure updates after the transaction is committed (postFlush) to ensure
 * only successfully persisted changes trigger real-time updates.
 */
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
class WatchFileMercureDoctrineListener
{
    /**
     * @var array<string, WatchFile>
     */
    private array $pendingUpdates = [];

    public function __construct(
        private readonly RealTimeUpdatePublisherInterface $publisher,
    ) {
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof WatchFile) {
            return;
        }

        // Track the WatchFile for publishing after flush completes
        $this->pendingUpdates[$entity->getId()] = $entity;
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (0 === \count($this->pendingUpdates)) {
            return;
        }

        // Process all pending updates
        $updates = $this->pendingUpdates;
        $this->pendingUpdates = [];

        foreach ($updates as $watchFile) {
            $this->publisher->publishWatchFileUpdate($watchFile);
        }
    }
}
