<?php

namespace App\Domain\User;

use App\Domain\WatchFile\WatchFile;

interface UserFavoriteWatchFileGatewayInterface
{
    public function getByUserAndWatchFile(User $user, WatchFile $watchFile): UserFavoriteWatchFile;

    /**
     * @param iterable<WatchFile> $watchFiles
     *
     * @return list<UserFavoriteWatchFile>
     */
    public function getByUserAndWatchFiles(User $user, iterable $watchFiles): array;

    public function save(UserFavoriteWatchFile $userFavoriteWatchFile): void;

    public function remove(UserFavoriteWatchFile $userFavoriteWatchFile): void;
}
