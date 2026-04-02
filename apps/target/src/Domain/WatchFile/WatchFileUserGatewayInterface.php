<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use App\Domain\User\User;

interface WatchFileUserGatewayInterface
{
    public function get(string $id): WatchFileUser;

    public function getByWatchFileAndUser(WatchFile $watchFile, User $user): WatchFileUser;

    public function save(WatchFileUser $watchFileUser): void;

    /**
     * @return list<WatchFileUser>
     */
    public function getByWatchFile(WatchFile $watchFile): array;

    public function remove(WatchFileUser $watchFileUser): void;

    public function countByWatchFile(WatchFile $watchFile): int;

    /**
     * @param iterable<WatchFile> $watchFiles
     *
     * @return array<string, int>
     */
    public function countByWatchFiles(iterable $watchFiles): array;

    /**
     * Check if users have edit access to multiple watch files.
     *
     * @param iterable<WatchFile> $watchFiles
     *
     * @return array<string, bool> Array with watch file IDs as keys and edit access as values
     */
    public function hasEditAccessForWatchFiles(User $user, iterable $watchFiles): array;

    /**
     * Get all users with real-time update access for a WatchFile.
     *
     * Returns users who have roles that allow receiving real-time updates
     * (owner and editor roles). This method eagerly fetches users in a single query
     * to avoid N+1 query issues.
     *
     * @return list<User>
     */
    public function getUsersWithRealTimeAccess(WatchFile $watchFile): array;
}
