<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect;

use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\CollectTaskNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

readonly class CollectTaskDoctrineGateway implements CollectTaskGatewayInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CollectTask $collectTask, bool $flush = true): void
    {
        $this->entityManager->persist($collectTask);
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function get(string $id): CollectTask
    {
        $collectTask = $this->entityManager->getRepository(CollectTask::class)->find($id);

        if (!$collectTask instanceof CollectTask) {
            throw CollectTaskNotFoundException::withId($id);
        }

        return $collectTask;
    }

    public function findActiveBySourceId(string $sourceId): array
    {
        return $this->entityManager
            ->getRepository(CollectTask::class)
            ->createQueryBuilder('ct')
            ->join('ct.source', 's')
            ->andWhere('s.id = :sourceId')
            ->setParameter('sourceId', $sourceId)
            ->andWhere('ct.status IN (:activeStatuses)')
            ->setParameter('activeStatuses', CollectTaskStatus::ACTIVE_STATUSES)
            ->orderBy('ct.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveTasksByWatchFileId(string $watchFileId): array
    {
        return $this->entityManager
            ->getRepository(CollectTask::class)
            ->createQueryBuilder('ct')
            ->join('ct.watchFile', 'wf')
            ->andWhere('wf.id = :watchFileId')
            ->setParameter('watchFileId', $watchFileId)
            ->andWhere('ct.status IN (:activeStatuses)')
            ->setParameter('activeStatuses', CollectTaskStatus::ACTIVE_STATUSES)
            ->orderBy('ct.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllByWatchFileId(string $watchFileId): array
    {
        return $this->entityManager
            ->getRepository(CollectTask::class)
            ->createQueryBuilder('ct')
            ->join('ct.watchFile', 'wf')
            ->andWhere('wf.id = :watchFileId')
            ->setParameter('watchFileId', $watchFileId)
            ->orderBy('ct.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
