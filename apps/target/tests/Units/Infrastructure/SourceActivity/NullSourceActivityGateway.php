<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\SourceActivity;

use App\Domain\Source\Source;
use App\Domain\SourceActivity\SourceActivity;
use App\Domain\SourceActivity\SourceActivityActionType;
use App\Domain\SourceActivity\SourceActivityGatewayInterface;

class NullSourceActivityGateway implements SourceActivityGatewayInterface
{
    /**
     * @var SourceActivity[]
     */
    private array $activities = [];

    public function save(SourceActivity $sourceActivity): void
    {
        $this->activities[] = $sourceActivity;
    }

    /**
     * @return array<string, array<SourceActivity>>
     */
    public function getBySourceGroupedByDay(Source $source, int $page = 1, int $itemsPerPage = 20): array
    {
        $sourceActivities = array_filter($this->activities, function (SourceActivity $activity) use ($source) {
            return $activity->getSource()
                ->getId() === $source->getId()
                && SourceActivityActionType::SOURCE_QUERY_LOG !== $activity->getActionType()
                && SourceActivityActionType::SOURCE_COLLECT_LOG !== $activity->getActionType();
        });

        $grouped = [];
        foreach ($sourceActivities as $activity) {
            $day = $activity->getCreatedAt()
                            ->format('Y-m-d');
            if (!isset($grouped[$day])) {
                $grouped[$day] = [];
            }
            $grouped[$day][] = $activity;
        }

        krsort($grouped);

        $offset = ($page - 1) * $itemsPerPage;

        return \array_slice($grouped, $offset, $itemsPerPage, true);
    }

    /**
     * @return SourceActivity[]
     */
    public function getAll(): array
    {
        return $this->activities;
    }
}
