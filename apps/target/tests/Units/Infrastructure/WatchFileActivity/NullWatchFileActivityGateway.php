<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFileActivity;

use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;
use App\Tests\Utils\ArrayPaginator;

class NullWatchFileActivityGateway implements WatchFileActivityGatewayInterface
{
    /**
     * @var array<string, WatchFileActivity>
     */
    private array $activities = [];

    public function save(WatchFileActivity $activity): void
    {
        $reflection = new \ReflectionClass($activity);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);

        if (null === $idProperty->getValue($activity)) {
            $idProperty->setValue($activity, 'test-activity-id-' . uniqid());
        }

        $this->activities[$activity->getId()] = $activity;
    }

    public function getByWatchFilePaginated(
        WatchFile $watchFile,
        int $page = 1,
        int $itemsPerPage = 30,
    ): PaginatorInterface {
        $watchFileActivities = array_filter(
            $this->activities,
            fn (WatchFileActivity $activity) => $activity->getWatchFile()
->getId() === $watchFile->getId()
        );

        // Sort activities by createdAt timestamp (most recent first)
        usort($watchFileActivities, function (WatchFileActivity $a, WatchFileActivity $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });

        $totalItems = \count($watchFileActivities);
        $offset = ($page - 1) * $itemsPerPage;
        $paginatedActivities = \array_slice($watchFileActivities, $offset, $itemsPerPage);

        /** @var PaginatorInterface<WatchFileActivity> $result */
        $result = new ArrayPaginator($paginatedActivities, $totalItems, $page, $itemsPerPage);

        return $result;
    }

    public function getByUserPaginated(User $user, int $page = 1, int $itemsPerPage = 30): PaginatorInterface
    {
        $userActivities = array_filter(
            $this->activities,
            fn (WatchFileActivity $activity) => $activity->getUser()
->getId() === $user->getId()
        );

        $totalItems = \count($userActivities);
        $offset = ($page - 1) * $itemsPerPage;
        $paginatedActivities = \array_slice(array_values($userActivities), $offset, $itemsPerPage);

        /** @var PaginatorInterface<WatchFileActivity> $result */
        $result = new ArrayPaginator($paginatedActivities, $totalItems, $page, $itemsPerPage);

        return $result;
    }

    /**
     * Get activities for a watchfile grouped by day, with each day's activities sorted by createdAt descending.
     *
     * @return array{activitiesByDay: array<string, array<WatchFileActivity>>, hasNextPage: bool}
     */
    public function getByWatchFileGroupedByDay(WatchFile $watchFile, int $page = 1, int $itemsPerPage = 30): array
    {
        $watchFileActivities = array_filter(
            $this->activities,
            fn (WatchFileActivity $activity) => $activity->getWatchFile()
->getId() === $watchFile->getId()
        );

        // Sort activities by createdAt timestamp (most recent first)
        usort($watchFileActivities, function (WatchFileActivity $a, WatchFileActivity $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });

        // Apply pagination
        $offset = ($page - 1) * $itemsPerPage;
        $paginatedActivities = \array_slice($watchFileActivities, $offset, $itemsPerPage);

        // Group activities by day
        $groupedActivities = [];
        foreach ($paginatedActivities as $activity) {
            $day = $activity->getCreatedAt()
->format('Y-m-d');
            if (!isset($groupedActivities[$day])) {
                $groupedActivities[$day] = [];
            }
            $groupedActivities[$day][] = $activity;
        }

        // Calculate hasNextPage - check if there are more activities beyond current page
        $totalActivities = \count($watchFileActivities);
        $currentPageEnd = $offset + $itemsPerPage;
        $hasNextPage = $currentPageEnd < $totalActivities;

        return [
            'activitiesByDay' => $groupedActivities,
            'hasNextPage' => $hasNextPage,
        ];
    }

    /**
     * Get all saved activities (for testing purposes).
     *
     * @return array<WatchFileActivity>
     */
    public function getAllSaved(): array
    {
        return array_values($this->activities);
    }
}
