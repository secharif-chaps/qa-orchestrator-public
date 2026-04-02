<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\Shared\EntityEnricherInterface;
use App\Domain\User\User;
use App\Domain\User\UserFavoriteWatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFile;

/**
 * @implements EntityEnricherInterface<WatchFile>
 */
class WatchFileFavoriteEnricher implements EntityEnricherInterface
{
    public function __construct(
        private readonly UserFavoriteWatchFileGatewayInterface $userFavoriteWatchFileGateway,
    ) {
    }

    public function enrich(object $entity, array $context = []): object
    {
        $user = $context['user'] ?? null;
        if ($user instanceof User) {
            $this->enrichWithPersonCounter([$entity], $user);
        }

        return $entity;
    }

    public function enrichCollection(iterable $entities, array $context = []): iterable
    {
        $user = $context['user'] ?? null;
        if ($user instanceof User) {
            return $this->enrichWithPersonCounter($entities, $user);
        }

        return $entities;
    }

    /**
     * @param iterable<WatchFile> $watchFiles
     *
     * @return iterable<WatchFile>
     */
    private function enrichWithPersonCounter(iterable $watchFiles, User $user): iterable
    {
        $userFavoriteWatchFiles = $this->userFavoriteWatchFileGateway->getByUserAndWatchFiles($user, $watchFiles);

        foreach ($userFavoriteWatchFiles as $userFavoriteWatchFile) {
            $watchFile = $userFavoriteWatchFile->getWatchFile();
            if ($watchFile instanceof WatchFile) {
                $watchFile->setIsFavorite(true);
            }
        }

        return $watchFiles;
    }

    public function supports(): string
    {
        return WatchFile::class;
    }
}
