<?php

declare(strict_types=1);

namespace App\Tests\Utils;

use ApiPlatform\State\Pagination\PaginatorInterface;

/**
 * @implements PaginatorInterface<object>
 * @implements \IteratorAggregate<mixed, object>
 */
class ArrayPaginator implements PaginatorInterface, \IteratorAggregate
{
    /**
     * @var array<mixed, object>
     */
    private array $items;
    private int $totalItems;
    private int $currentPage;
    private int $itemsPerPage;

    /**
     * @param array<mixed, object> $items
     */
    public function __construct(
        array $items,
        ?int $totalItems = null,
        int $currentPage = 1,
        int $itemsPerPage = 30,
    ) {
        $this->items = $items;
        $this->totalItems = $totalItems ?? \count($items);
        $this->currentPage = $currentPage;
        $this->itemsPerPage = $itemsPerPage;
    }

    /**
     * @return \Traversable<mixed, object>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return \count($this->items);
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
