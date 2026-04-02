<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\SourceActivity;

use App\Domain\SourceActivity\SourceActivity;
use Symfony\Component\Serializer\Annotation\Groups;

readonly class GroupedSourceActivityDto
{
    /**
     * @param array<string, array<SourceActivity>> $activitiesByDay
     */
    public function __construct(
        #[Groups(['grouped_source_activity:read'])]
        private readonly array $activitiesByDay,
    ) {
    }

    /**
     * @return array<string, array<SourceActivity>>
     */
    public function getActivitiesByDay(): array
    {
        return $this->activitiesByDay;
    }

    #[Groups(['grouped_source_activity:read'])]
    public function getTotalItems(): int
    {
        return array_sum(array_map('count', $this->activitiesByDay));
    }

    /**
     * Get the total number of activities (alias for getTotalItems for compatibility).
     */
    public function count(): int
    {
        return $this->getTotalItems();
    }
}
