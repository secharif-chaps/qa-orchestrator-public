<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\State\Pagination\PaginatorInterface;

/**
 * @phpstan-ignore-next-line missingType.generics
 */
class TimelinePaginator implements PaginatorInterface, \IteratorAggregate
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $events;
    private int $totalItems;
    private int $currentPage;
    private int $itemsPerPage;

    /**
     * @param array<int, array<string, mixed>> $events
     */
    public function __construct(array $events, int $totalItems, int $currentPage, int $itemsPerPage)
    {
        $this->events = $events;
        $this->totalItems = $totalItems;
        $this->currentPage = $currentPage;
        $this->itemsPerPage = $itemsPerPage;
    }

    /**
     * @return \Traversable<mixed, array<string, mixed>>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->events);
    }

    public function count(): int
    {
        return \count($this->events);
    }

    public function getLastPage(): float
    {
        if (0 === $this->itemsPerPage) {
            return 1.0;
        }

        return ceil($this->totalItems / $this->itemsPerPage);
    }

    public function getTotalItems(): float
    {
        return (float) $this->totalItems;
    }

    public function getCurrentPage(): float
    {
        return (float) $this->currentPage;
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->itemsPerPage;
    }
}
