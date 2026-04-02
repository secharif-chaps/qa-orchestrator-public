<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

interface SearchQueryGatewayInterface
{
    public function save(SearchQuery $searchQuery): void;

    public function get(string $searchQueryId): SearchQuery;

    public function findByTermAndStrategicQuestion(
        string $searchTerm,
        string $country,
        string $language,
        string $strategicQuestionId,
    ): ?SearchQuery;
}
