<?php

declare(strict_types=1);

namespace App\Infrastructure\Pagination;

use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Domain\WatchFileEvent\WatchFileEvent;
use App\UserInterface\Dto\WatchFileEvent\EventGraphEntryDto;

/**
 * Paginator decorator that exposes OpenSearch aggregations for WatchFileEvent graphs.
 *
 * @extends PaginatorWithAggregations<EventGraphEntryDto>
 */
readonly class WatchFileEventPaginatorWithAggregations extends PaginatorWithAggregations
{
    /**
     * @param PaginatorInterface<EventGraphEntryDto> $paginator
     * @param array<string, mixed>                   $aggregations
     */
    public function __construct(PaginatorInterface $paginator, array $aggregations)
    {
        parent::__construct($paginator, $aggregations, WatchFileEvent::class);
    }
}
