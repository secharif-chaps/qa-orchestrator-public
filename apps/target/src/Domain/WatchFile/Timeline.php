<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Timeline resource representing the chronological events of a WatchFile.
 * This is a virtual resource that doesn't correspond to a database entity.
 */
#[ApiResource(mercure: false)]
class Timeline
{
    /**
     * @param array<int, array<string, mixed>> $events
     */
    public function __construct(
        #[Groups(['timeline:read'])]
        private array $events = [],

        #[Groups(['timeline:read'])]
        private int $totalItems = 0,

        #[Groups(['timeline:read'])]
        private int $currentPage = 1,

        #[Groups(['timeline:read'])]
        private int $itemsPerPage = 30,

        #[Groups(['timeline:read'])]
        private int $totalPages = 1,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->totalPages;
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }
}
