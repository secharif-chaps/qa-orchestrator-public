<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\Shared\EntityEnricherInterface;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileUserGatewayInterface;

/**
 * @implements EntityEnricherInterface<WatchFile>
 */
class WatchFilePersonCounterEnricher implements EntityEnricherInterface
{
    public function __construct(
        private readonly WatchFileUserGatewayInterface $watchFileUserGateway,
    ) {
    }

    public function enrich(object $entity, array $context = []): object
    {
        $this->enrichWithPersonCounter([$entity]);

        return $entity;
    }

    public function enrichCollection(iterable $entities, array $context = []): iterable
    {
        return $this->enrichWithPersonCounter($entities);
    }

    /**
     * @param iterable<WatchFile> $watchFiles
     *
     * @return iterable<WatchFile>
     */
    public function enrichWithPersonCounter(iterable $watchFiles): iterable
    {
        $counters = $this->watchFileUserGateway->countByWatchFiles($watchFiles);

        foreach ($watchFiles as $watchFile) {
            if (isset($counters[$watchFile->getId()])) {
                $watchFile->setWatchFileUsersCount($counters[$watchFile->getId()]);
            }
        }

        return $watchFiles;
    }

    public function supports(): string
    {
        return WatchFile::class;
    }
}
