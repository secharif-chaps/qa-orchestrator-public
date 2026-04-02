<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\WatchFile\Exception\SearchQueryNotFoundException;
use App\Domain\WatchFile\SearchQuery;
use App\Domain\WatchFile\SearchQueryGatewayInterface;
use Doctrine\ORM\EntityManagerInterface;

class SearchQueryDoctrineGateway implements SearchQueryGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(SearchQuery $searchQuery): void
    {
        $this->entityManager->persist($searchQuery);
        $this->entityManager->flush();
    }

    public function get(string $searchQueryId): SearchQuery
    {
        $searchQuery = $this->entityManager->find(SearchQuery::class, $searchQueryId);

        if (null === $searchQuery) {
            throw new SearchQueryNotFoundException(\sprintf('SearchQuery with ID "%s" not found.', $searchQueryId));
        }

        return $searchQuery;
    }

    public function findByTermAndStrategicQuestion(
        string $searchTerm,
        string $country,
        string $language,
        string $strategicQuestionId,
    ): ?SearchQuery {
        /** @var SearchQuery|null $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('sq')
            ->from(SearchQuery::class, 'sq')
            ->where('sq.searchTermHash = :searchTermHash')
            ->andWhere('sq.country = :country')
            ->andWhere('sq.language = :language')
            ->andWhere('sq.strategicQuestion = :strategicQuestionId')
            ->setParameter('searchTermHash', SearchQuery::makeSearchTermHash(searchTerm: $searchTerm))
            ->setParameter('country', $country)
            ->setParameter('language', $language)
            ->setParameter('strategicQuestionId', $strategicQuestionId)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }
}
