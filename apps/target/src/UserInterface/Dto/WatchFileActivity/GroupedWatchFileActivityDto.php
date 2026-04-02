<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\WatchFileActivity;

use App\Domain\WatchFileActivity\WatchFileActivity;
use Symfony\Component\Serializer\Annotation\Groups;

readonly class GroupedWatchFileActivityDto
{
    /**
     * @param array<string, array<WatchFileActivity>> $activitiesByDay
     */
    public function __construct(
        #[Groups(['grouped_watch_file_activity:read'])]
        private readonly array $activitiesByDay,
        #[Groups(['grouped_watch_file_activity:read'])]
        private readonly bool $hasNextPage = false,
    ) {
    }

    /**
     * @return array<string, array<WatchFileActivity>>
     */
    public function getActivitiesByDay(): array
    {
        return $this->activitiesByDay;
    }

    #[Groups(['grouped_watch_file_activity:read'])]
    public function getTotalItems(): int
    {
        return \count($this->activitiesByDay);
    }

    /**
     * Get the total number of activities across all days (alias for compatibility).
     */
    #[Groups(['grouped_watch_file_activity:read'])]
    public function getTotalActivities(): int
    {
        return array_sum(array_map('count', $this->activitiesByDay));
    }

    #[Groups(['grouped_watch_file_activity:read'])]
    public function getHasNextPage(): bool
    {
        return $this->hasNextPage;
    }
}
