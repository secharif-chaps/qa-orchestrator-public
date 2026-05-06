<?php

declare(strict_types=1);

namespace App\Domain\WatchFileActivity;

use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;

interface WatchFileActivityGatewayInterface
{
    public function save(WatchFileActivity $watchFileActivity): void;

    /**
     * @return PaginatorInterface<WatchFileActivity>
     */
    public function getByWatchFilePaginated(
        WatchFile $watchFile,
        int $page = 1,
        int $itemsPerPage = 30,
    ): PaginatorInterface;

    /**
     * @return PaginatorInterface<WatchFileActivity>
     */
    public function getByUserPaginated(User $user, int $page = 1, int $itemsPerPage = 30): PaginatorInterface;

    /**
     * Get activities for a watchfile grouped by day, with each day's activities sorted by createdAt descending.
     *
     * @return array{activitiesByDay: array<string, array<WatchFileActivity>>, hasNextPage: bool}
     */
    public function getByWatchFileGroupedByDay(WatchFile $watchFile, int $page = 1, int $itemsPerPage = 30): array;
}
