<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\Shared\EntityEnrichmentOrchestratorInterface;
use App\Domain\UsageLimit\ResourceCount;
use App\Domain\User\User;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileActor;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;
use Doctrine\ORM\EntityManagerInterface;

class WatchFileDoctrineGateway implements WatchFileGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntityEnrichmentOrchestratorInterface $watchFileEnricher,
    ) {
    }

    public function get(string $id, ?User $user = null): WatchFile
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('f')
            ->addSelect('fu')
            ->from(WatchFile::class, 'f')
            ->leftJoin('f.watchFileUsers', 'fu')
            ->where('f.id = :id')
            ->setParameter('id', $id)
        ;

        $watchFile = $queryBuilder
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (!$watchFile instanceof WatchFile) {
            throw new WatchFileNotFoundException(\sprintf('WatchFile with id %s not found', $id));
        }

        return $this->watchFileEnricher->enrich($watchFile, [
            'user' => $user,
        ]);
    }

    public function save(WatchFile $watchFile): void
    {
        $watchFile->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($watchFile);
        $this->entityManager->flush();
    }

    public function getForUser(string $id, User $user): WatchFile
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $queryBuilder->select('f')
            ->from(WatchFile::class, 'f')
            ->leftJoin('f.watchFileUsers', 'fu')
            ->where('f.id = :id')
            ->andWhere('fu.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
        ;

        $watchFile = $queryBuilder
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (!$watchFile instanceof WatchFile) {
            throw new WatchFileNotFoundException(\sprintf(
                'WatchFile with id %s is not found or it not accessible for user %s',
                $id,
                $user->getId()
            ), );
        }

        return $this->watchFileEnricher->enrich($watchFile, [
            'user' => $user,
        ]);
    }

    public function findAllIdsByUser(User $user): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        /** @var list<string> $result */
        $result = $queryBuilder->select('IDENTITY(fu.watchFile)')
            ->from(WatchFileUser::class, 'fu')
            ->where('fu.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult();

        return $result;
    }

    public function countActiveByUserId(string $userId): ResourceCount
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $count = $queryBuilder->select('COUNT(DISTINCT f.id)')
            ->from(WatchFile::class, 'f')
            ->leftJoin('f.watchFileUsers', 'wfu')
            ->where('wfu.user = :userId')
            ->andWhere('wfu.role = :role')
            ->andWhere('f.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('role', WatchFileUserRole::OWNER)
            ->setParameter('status', WatchFileStatus::ENABLED)
            ->getQuery()
            ->getSingleScalarResult();

        return ResourceCount::fromInt((int) $count);
    }

    public function countNonArchivedByOwnerId(string $userId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT f.id)')
            ->from(WatchFile::class, 'f')
            ->innerJoin('f.watchFileUsers', 'fu')
            ->where('fu.user = :userId')
            ->andWhere('fu.role = :ownerRole')
            ->andWhere('f.status != :archivedStatus')
            ->setParameter('userId', $userId)
            ->setParameter('ownerRole', WatchFileUserRole::OWNER)
            ->setParameter('archivedStatus', WatchFileStatus::ARCHIVED->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasActorRelation(string $watchFileId, string $actorId): bool
    {
        $count = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(wfa.id)')
            ->from(WatchFileActor::class, 'wfa')
            ->where('wfa.watchFile = :watchFileId')
            ->andWhere('wfa.actor = :actorId')
            ->setParameter('watchFileId', $watchFileId)
            ->setParameter('actorId', $actorId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
