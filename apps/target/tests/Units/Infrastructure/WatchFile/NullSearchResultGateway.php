<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use App\Domain\WatchFile\Exception\SearchResultNotFoundException;
use App\Domain\WatchFile\SearchResult;
use App\Domain\WatchFile\SearchResultGatewayInterface;
use App\Tests\Utils\EntityUtilsTrait;
use Symfony\Component\Uid\Uuid;

class NullSearchResultGateway implements SearchResultGatewayInterface
{
    use EntityUtilsTrait;

    /**
     * @var array<string, SearchResult>
     */
    private array $searchResults = [];

    public function save(SearchResult $searchResult): void
    {
        if (null === $searchResult->getId()) {
            $this->forcePropertyValue($searchResult, Uuid::v4());
        }

        $this->searchResults[$searchResult->getId()] = $searchResult;
    }

    public function get(string $searchResultId): SearchResult
    {
        if (!isset($this->searchResults[$searchResultId])) {
            throw new SearchResultNotFoundException(\sprintf('SearchResult with ID "%s" not found.', $searchResultId));
        }

        return $this->searchResults[$searchResultId];
    }

    public function findByUrlAndSearchQuery(string $url, string $searchQueryId): ?SearchResult
    {
        foreach ($this->searchResults as $searchResult) {
            if ($searchResult->getUrl() === $url
                && $searchResult->getSearchQuery()
->getId() === $searchQueryId) {
                return $searchResult;
            }
        }

        return null;
    }

    public function countBySearchQuery(string $searchQueryId): int
    {
        $count = 0;
        foreach ($this->searchResults as $searchResult) {
            if ($searchResult->getSearchQuery()->getId() === $searchQueryId) {
                ++$count;
            }
        }

        return $count;
    }
}
