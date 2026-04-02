<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\WatchFile\SearchResult;
use App\Domain\WatchFile\SearchResultExtraction;
use App\Domain\WatchFile\SearchResultExtractionGatewayInterface;
use Doctrine\ORM\EntityManagerInterface;

class SearchResultExtractionDoctrineGateway implements SearchResultExtractionGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(SearchResultExtraction $extraction): void
    {
        $this->entityManager->persist($extraction);
    }

    /**
     * @return array<int, SearchResultExtraction>
     */
    public function findBySearchResult(SearchResult $searchResult): array
    {
        /** @var array<int, SearchResultExtraction> $extractions */
        $extractions = $this->entityManager->createQueryBuilder()
            ->select('sre')
            ->from(SearchResultExtraction::class, 'sre')
            ->where('sre.searchResult = :searchResult')
            ->setParameter('searchResult', $searchResult)
            ->orderBy('sre.extractedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $extractions;
    }
}
