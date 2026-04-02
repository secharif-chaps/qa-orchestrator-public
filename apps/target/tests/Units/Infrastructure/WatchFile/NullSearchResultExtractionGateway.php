<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use App\Domain\WatchFile\SearchResult;
use App\Domain\WatchFile\SearchResultExtraction;
use App\Domain\WatchFile\SearchResultExtractionGatewayInterface;
use App\Tests\Utils\EntityUtilsTrait;
use Symfony\Component\Uid\Uuid;

class NullSearchResultExtractionGateway implements SearchResultExtractionGatewayInterface
{
    use EntityUtilsTrait;

    /**
     * @var array<string, SearchResultExtraction>
     */
    private array $extractions = [];

    public function save(SearchResultExtraction $extraction): void
    {
        if (null === $extraction->getId()) {
            $this->forcePropertyValue($extraction, Uuid::v4(), 'id');
        }

        $this->extractions[$extraction->getId()] = $extraction;
    }

    /**
     * @return array<int, SearchResultExtraction>
     */
    public function findBySearchResult(SearchResult $searchResult): array
    {
        $results = [];
        foreach ($this->extractions as $extraction) {
            if ($extraction->getSearchResult()->getId() === $searchResult->getId()) {
                $results[] = $extraction;
            }
        }

        return $results;
    }

    /**
     * @return array<string, SearchResultExtraction>
     */
    public function getAll(): array
    {
        return $this->extractions;
    }

    public function count(): int
    {
        return \count($this->extractions);
    }
}
