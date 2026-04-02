<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\WatchFile\Exception\SearchResultNotFoundException;
use App\Domain\WatchFile\SearchResult;
use App\Domain\WatchFile\SearchResultGatewayInterface;
use Doctrine\ORM\EntityManagerInterface;

class SearchResultDoctrineGateway implements SearchResultGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(SearchResult $searchResult): void
    {
        $this->entityManager->persist($searchResult);
        $this->entityManager->flush();
    }

    public function get(string $searchResultId): SearchResult
    {
        $searchResult = $this->entityManager->find(SearchResult::class, $searchResultId);

        if (null === $searchResult) {
            throw new SearchResultNotFoundException(\sprintf('SearchResult with ID "%s" not found.', $searchResultId));
        }

        return $searchResult;
    }

    public function findByUrlAndSearchQuery(string $url, string $searchQueryId): ?SearchResult
    {
        /** @var SearchResult|null $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('sr')
            ->from(SearchResult::class, 'sr')
            ->where('sr.url = :url')
            ->andWhere('sr.searchQuery = :searchQueryId')
            ->setParameter('url', $url)
            ->setParameter('searchQueryId', $searchQueryId)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function countBySearchQuery(string $searchQueryId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(sr.id)')
            ->from(SearchResult::class, 'sr')
            ->where('sr.searchQuery = :searchQueryId')
            ->setParameter('searchQueryId', $searchQueryId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
