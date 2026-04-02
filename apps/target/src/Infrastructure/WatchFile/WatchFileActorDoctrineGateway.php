<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorStatus;
use App\Domain\Actor\ActorType;
use App\Domain\WatchFile\Exception\WatchFileActorNotFoundException;
use App\Domain\WatchFile\WatchFileActor;
use App\Domain\WatchFile\WatchFileActorGatewayInterface;
use Doctrine\ORM\EntityManagerInterface;

class WatchFileActorDoctrineGateway implements WatchFileActorGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findByActorAndWatchFile(string $actorId, string $watchFileId): WatchFileActor
    {
        $result = $this->entityManager->getRepository(WatchFileActor::class)->findOneBy([
            'actor' => $actorId,
            'watchFile' => $watchFileId,
        ]);
        if (!$result) {
            throw new WatchFileActorNotFoundException();
        }

        return $result;
    }

    public function save(WatchFileActor $watchFileActor): void
    {
        $this->entityManager->persist($watchFileActor);
        $this->entityManager->flush();
    }

    /**
     * @return array<int, array{type: string, count: int}>
     */
    public function getActorTypesCounts(string $watchFileId, ?ActorStatus $status = null, ?string $name = null): array
    {
        $queryBuilder = $this->entityManager->getRepository(WatchFileActor::class)
            ->createQueryBuilder('wfa')
            ->select('wfa.type as type, COUNT(wfa.id) as count')
            ->where('wfa.watchFile = :watchFileId')
            ->groupBy('wfa.type')
            ->setParameter('watchFileId', $watchFileId);

        if (null !== $status) {
            $queryBuilder
                ->andWhere('wfa.status = :status')
                ->setParameter('status', $status);
        }

        if (null !== $name && '' !== $name) {
            $queryBuilder
                ->join('wfa.actor', 'a')
                ->andWhere('LOWER(a.label) LIKE LOWER(:name)')
                ->setParameter('name', '%' . addcslashes($name, '%_\\') . '%');
        }

        $result = $queryBuilder
            ->getQuery()
            ->getArrayResult();

        /** @var array<int, array{type: string, count: int}> */
        return array_map(
            function ($row): array {
                if (!\is_array($row) || !isset($row['type']) || !isset($row['count'])) {
                    throw new \RuntimeException('Invalid result row structure');
                }

                $type = $row['type'];
                $count = $row['count'];

                if ($type instanceof ActorType) {
                    $typeValue = $type->value;
                } elseif (\is_string($type)) {
                    $typeValue = $type;
                } else {
                    throw new \RuntimeException('Invalid type value in result row');
                }

                if (!is_numeric($count)) {
                    throw new \RuntimeException('Invalid count value in result row');
                }

                return [
                    'type' => $typeValue,
                    'count' => (int) $count,
                ];
            },
            $result
        );
    }

    public function findActorByNormalizedDomain(string $watchFileId, string $normalizedDomain): ?Actor
    {
        // Use native SQL query for efficient domain matching with normalization
        // Normalization matches DomainMatcher logic: lowercase, trim, strip www prefix
        // This performs the normalization directly in PostgreSQL for optimal performance
        $sql = <<<'SQL'
                SELECT a.id
                FROM watch_file_actor wfa
                INNER JOIN actor a ON wfa.actor_id = a.id
                WHERE wfa.watch_file_id = :watchFileId
                  AND a.primary_domain IS NOT NULL
                  AND a.primary_domain != ''
                  AND (
                    -- Direct match: normalized domain equals search domain
                    LOWER(TRIM(a.primary_domain)) = :normalizedDomain
                    OR
                    -- Actor domain with www prefix matches normalized domain
                    LOWER(TRIM(a.primary_domain)) = 'www.' || :normalizedDomain
                    OR
                    -- Actor domain without www matches normalized domain (strip www if present)
                    (
                      LOWER(TRIM(a.primary_domain)) LIKE 'www.%'
                      AND SUBSTRING(LOWER(TRIM(a.primary_domain)) FROM 5) = :normalizedDomain
                    )
                  )
                LIMIT 1
            SQL;

        $connection = $this->entityManager->getConnection();
        $result = $connection->executeQuery(
            $sql,
            [
                'watchFileId' => $watchFileId,
                'normalizedDomain' => $normalizedDomain,
            ]
        );

        $row = $result->fetchAssociative();
        if (false === $row || !isset($row['id'])) {
            return null;
        }

        // Load the actor entity using the found ID
        $actor = $this->entityManager->getRepository(Actor::class)->find($row['id']);

        return $actor instanceof Actor ? $actor : null;
    }
}
