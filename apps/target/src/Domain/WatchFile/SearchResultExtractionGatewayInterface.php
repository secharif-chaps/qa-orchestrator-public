<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

interface SearchResultExtractionGatewayInterface
{
    public function save(SearchResultExtraction $extraction): void;

    /**
     * @return array<int, SearchResultExtraction>
     */
    public function findBySearchResult(SearchResult $searchResult): array;
}
