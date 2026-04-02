<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\WatchFileEvent;

use Symfony\Component\Serializer\Annotation\Groups;

class EventGraphEntryDto
{
    #[Groups(['event_graph:read'])]
    public int $documentsCount;

    #[Groups(['event_graph:read'])]
    public int $eventsCount;

    #[Groups(['event_graph:read'])]
    public bool $hasEvents;

    #[Groups(['event_graph:read'])]
    public \DateTimeImmutable $start;

    #[Groups(['event_graph:read'])]
    public \DateTimeImmutable $end;

    #[Groups(['event_graph:read'])]
    public string $link;

    public function __construct(
        int $documentsCount,
        int $eventsCount,
        bool $hasEvents,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $link,
    ) {
        $this->documentsCount = $documentsCount;
        $this->eventsCount = $eventsCount;
        $this->hasEvents = $hasEvents;
        $this->start = $start;
        $this->end = $end;
        $this->link = $link;
    }
}
