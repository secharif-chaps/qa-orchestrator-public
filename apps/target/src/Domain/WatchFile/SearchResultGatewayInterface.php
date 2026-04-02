<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

interface SearchResultGatewayInterface
{
    public function save(SearchResult $searchResult): void;

    public function get(string $searchResultId): SearchResult;

    public function findByUrlAndSearchQuery(string $url, string $searchQueryId): ?SearchResult;

    public function countBySearchQuery(string $searchQueryId): int;
}
