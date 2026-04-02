<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\Shared\EntityEnricherInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileUserGatewayInterface;

/**
 * @implements EntityEnricherInterface<WatchFile>
 */
class WatchFileUserEditableEnricher implements EntityEnricherInterface
{
    public function __construct(
        private readonly WatchFileUserGatewayInterface $watchFileUserGateway,
    ) {
    }

    public function enrich(object $entity, array $context = []): object
    {
        $user = $context['user'] ?? null;
        if ($user instanceof User) {
            $this->enrichWithUserEditable([$entity], $user);
        }

        return $entity;
    }

    public function enrichCollection(iterable $entities, array $context = []): iterable
    {
        $user = $context['user'] ?? null;
        if ($user instanceof User) {
            return $this->enrichWithUserEditable($entities, $user);
        }

        return $entities;
    }

    /**
     * @param iterable<WatchFile> $watchFiles
     *
     * @return iterable<WatchFile>
     */
    private function enrichWithUserEditable(iterable $watchFiles, User $user): iterable
    {
        $editAccessMap = $this->watchFileUserGateway->hasEditAccessForWatchFiles($user, $watchFiles);

        foreach ($watchFiles as $watchFile) {
            $watchFileId = $watchFile->getId();
            if ($watchFileId && isset($editAccessMap[$watchFileId])) {
                $watchFile->setUserEditable($editAccessMap[$watchFileId]);
            }
        }

        return $watchFiles;
    }

    public function supports(): string
    {
        return WatchFile::class;
    }
}
