<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\User;

use App\Domain\User\User;
use App\Domain\User\UserFavoriteWatchFile;
use App\Domain\User\UserFavoriteWatchFileGatewayInterface;
use App\Domain\User\UserFavoriteWatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;

class NullUserFavoriteWatchFileGateway implements UserFavoriteWatchFileGatewayInterface
{
    /** @var array<string, UserFavoriteWatchFile> */
    private array $favorites = [];

    public function getByUserAndWatchFile(User $user, WatchFile $watchFile): UserFavoriteWatchFile
    {
        $key = $this->getKey($user, $watchFile);
        if (!isset($this->favorites[$key])) {
            throw new UserFavoriteWatchFileNotFoundException();
        }

        return $this->favorites[$key];
    }

    /**
     * @param iterable<WatchFile> $watchFiles
     *
     * @return list<UserFavoriteWatchFile>
     */
    public function getByUserAndWatchFiles(User $user, iterable $watchFiles): array
    {
        $result = [];
        foreach ($watchFiles as $watchFile) {
            $key = $this->getKey($user, $watchFile);
            if (isset($this->favorites[$key])) {
                $result[] = $this->favorites[$key];
            }
        }

        return $result;
    }

    public function save(UserFavoriteWatchFile $userFavoriteWatchFile): void
    {
        $key = $this->getKey($userFavoriteWatchFile->getUser(), $userFavoriteWatchFile->getWatchFile());
        $this->favorites[$key] = $userFavoriteWatchFile;
    }

    public function remove(UserFavoriteWatchFile $userFavoriteWatchFile): void
    {
        $key = $this->getKey($userFavoriteWatchFile->getUser(), $userFavoriteWatchFile->getWatchFile());
        unset($this->favorites[$key]);
    }

    private function getKey(?User $user, ?WatchFile $watchFile): string
    {
        return ($user?->getId() ?? 'null') . ':' . ($watchFile?->getId() ?? 'null');
    }
}
