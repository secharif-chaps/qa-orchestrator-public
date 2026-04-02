<?php

declare(strict_types=1);

namespace App\Infrastructure\Pagination;

use ApiPlatform\State\Pagination\PaginatorInterface;

/**
 * Paginator decorator that exposes OpenSearch aggregations.
 *
 * @template T of object
 *
 * @implements PaginatorInterface<T>
 * @implements \IteratorAggregate<T>
 */
readonly class PaginatorWithAggregations implements \IteratorAggregate, PaginatorInterface
{
    /**
     * @param PaginatorInterface<T> $paginator
     * @param array<string, mixed>  $aggregations
     * @param class-string|null     $resourceClass The resource class this paginator represents (used for normalizer selection)
     *                                             Note: This may differ from T when the paginator contains DTOs but facets are for a different resource
     */
    public function __construct(
        public PaginatorInterface $paginator,
        public array $aggregations,
        public ?string $resourceClass = null,
    ) {
    }

    public function count(): int
    {
        return $this->paginator->count();
    }

    public function getLastPage(): float
    {
        return $this->paginator->getLastPage();
    }

    public function getTotalItems(): float
    {
        return $this->paginator->getTotalItems();
    }

    public function getCurrentPage(): float
    {
        return $this->paginator->getCurrentPage();
    }

    public function getItemsPerPage(): float
    {
        return $this->paginator->getItemsPerPage();
    }

    /**
     * @return \Traversable<T>
     */
    public function getIterator(): \Traversable
    {
        if ($this->paginator instanceof \IteratorAggregate) {
            /** @var \Traversable<T> */
            return $this->paginator->getIterator();
        }

        throw new \LogicException('Paginator must implement IteratorAggregate');
    }
}
