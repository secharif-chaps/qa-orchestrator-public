<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use App\Domain\WatchFile\Exception\SearchQueryNotFoundException;
use App\Domain\WatchFile\SearchQuery;
use App\Domain\WatchFile\SearchQueryGatewayInterface;
use App\Tests\Utils\EntityUtilsTrait;
use Symfony\Component\Uid\Uuid;

class NullSearchQueryGateway implements SearchQueryGatewayInterface
{
    use EntityUtilsTrait;

    /**
     * @var array<string, SearchQuery>
     */
    private array $searchQueries = [];

    public function save(SearchQuery $searchQuery): void
    {
        if (null === $searchQuery->getId()) {
            $this->forcePropertyValue($searchQuery, Uuid::v4());
        }

        $this->searchQueries[$searchQuery->getId()] = $searchQuery;
    }

    public function get(string $searchQueryId): SearchQuery
    {
        if (!isset($this->searchQueries[$searchQueryId])) {
            throw new SearchQueryNotFoundException(\sprintf('SearchQuery with ID "%s" not found.', $searchQueryId));
        }

        return $this->searchQueries[$searchQueryId];
    }

    public function findByTermAndStrategicQuestion(
        string $searchTerm,
        string $country,
        string $language,
        string $strategicQuestionId,
    ): ?SearchQuery {
        foreach ($this->searchQueries as $searchQuery) {
            if ($searchQuery->getSearchTerm() === $searchTerm
                && $searchQuery->getCountry() === $country
                && $searchQuery->getLanguage() === $language
                && $searchQuery->getStrategicQuestion()
->getId() === $strategicQuestionId) {
                return $searchQuery;
            }
        }

        return null;
    }
}
