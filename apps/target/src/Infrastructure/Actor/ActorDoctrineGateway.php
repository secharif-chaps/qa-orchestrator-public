<?php

declare(strict_types=1);

namespace App\Infrastructure\Actor;

use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorGatewayInterface;
use App\Domain\Actor\ActorNotFoundException;
use App\Domain\WatchFile\WatchFileActor;
use Doctrine\ORM\EntityManagerInterface;

readonly class ActorDoctrineGateway implements ActorGatewayInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getByLabel(string $label): Actor
    {
        $actor = $this->entityManager->getRepository(Actor::class)->findOneByLabel($label);

        if (!$actor instanceof Actor) {
            throw new ActorNotFoundException('Actor not found for label ' . $label);
        }

        return $actor;
    }

    /**
     * @param array<int, string> $labels
     *
     * @return array<string, Actor>
     */
    public function getByLabels(array $labels): array
    {
        if (empty($labels)) {
            return [];
        }

        $actors = $this->entityManager->getRepository(Actor::class)
            ->createQueryBuilder('a')
            ->where('a.label IN (:labels)')
            ->setParameter('labels', $labels)
            ->getQuery()
            ->getResult();

        $actorMap = [];
        foreach ($actors as $actor) {
            $actorMap[$actor->getLabel()] = $actor;
        }

        return $actorMap;
    }

    public function get(string $id): Actor
    {
        $actor = $this->entityManager->getRepository(Actor::class)->find($id);

        if (!$actor instanceof Actor) {
            throw new ActorNotFoundException('Actor not found for id ' . $id);
        }

        return $actor;
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $actors = $this->entityManager->getRepository(Actor::class)
            ->createQueryBuilder('a')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $actorsById = [];
        foreach ($actors as $actor) {
            $actorsById[$actor->getId()] = $actor;
        }

        return $actorsById;
    }

    public function save(Actor $actor): void
    {
        $this->entityManager->persist($actor);
        $this->entityManager->flush();
    }

    public function countByWatchFileId(string $watchFileId): int
    {
        return (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(wfa.id)')
            ->from(WatchFileActor::class, 'wfa')
            ->where('wfa.watchFile = :watchFileId')
            ->setParameter('watchFileId', $watchFileId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByPrimaryDomain(string $primaryDomain): ?Actor
    {
        return $this->entityManager->getRepository(Actor::class)->findOneBy([
            'primaryDomain' => $primaryDomain,
        ]);
    }

    public function getByWatchFileAndId(string $actorId, string $watchFileId): ?Actor
    {
        $watchFileActor = $this->entityManager->getRepository(WatchFileActor::class)
            ->createQueryBuilder('wfa')
            ->innerJoin('wfa.actor', 'a')
            ->where('a.id = :actorId')
            ->andWhere('wfa.watchFile = :watchFileId')
            ->setParameter('actorId', $actorId)
            ->setParameter('watchFileId', $watchFileId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$watchFileActor instanceof WatchFileActor) {
            return null;
        }

        return $watchFileActor->getActor();
    }
}
