<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\User\User;
use App\Domain\WatchFile\Exception\WatchFileUserNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserGatewayInterface;
use App\Domain\WatchFile\WatchFileUserRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;

class WatchFileUserDoctrineGateway implements WatchFileUserGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function get(string $id): WatchFileUser
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('fu')
            ->from(WatchFileUser::class, 'fu')
            ->where('fu.id = :id')
            ->setParameter('id', $id)
        ;

        $watchFileUser = $queryBuilder
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (!$watchFileUser instanceof WatchFileUser) {
            throw new WatchFileUserNotFoundException('WatchFileUser not found for id: ' . $id);
        }

        return $watchFileUser;
    }

    public function getByWatchFileAndUser(WatchFile $watchFile, User $user): WatchFileUser
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('fu')
            ->from(WatchFileUser::class, 'fu')
            ->where('fu.watchFile = :watchfile')
            ->andWhere('fu.user = :user')
            ->setParameter('watchfile', $watchFile)
            ->setParameter('user', $user)
        ;

        $watchFileUser = $queryBuilder
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (!$watchFileUser instanceof WatchFileUser) {
            throw new WatchFileUserNotFoundException(
                'WatchFileUser not found for watch file: ' . $watchFile->getId() . ' and user: ' . $user->getId()
            );
        }

        return $watchFileUser;
    }

    public function save(WatchFileUser $watchFileUser): void
    {
        $this->entityManager->persist($watchFileUser);
        $this->entityManager->flush();
    }

    public function getByWatchFile(WatchFile $watchFile): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $queryBuilder->select('fu', 'fuu')
            ->addSelect('CASE WHEN fu.role = :ownerRole THEN 1 ELSE 0 END AS HIDDEN isOwner')
            ->setParameter('ownerRole', WatchFileUserRole::OWNER->value)
            ->addSelect(
                'CASE WHEN fuu.firstName IS NOT NULL AND fuu.lastName IS NOT NULL THEN CONCAT(fuu.firstName, \' \', fuu.lastName) ELSE fuu.userName END AS HIDDEN displayName'
            )
            ->from(WatchFileUser::class, 'fu')
            ->join('fu.user', 'fuu')
            ->where('fu.watchFile = :watchfile')
            ->setParameter('watchfile', $watchFile)
            ->orderBy('isOwner', 'DESC')
            ->addOrderBy('displayName', 'ASC')
            ->addOrderBy('fu.createdAt', 'ASC')
        ;

        return $queryBuilder
            ->getQuery()
            ->getResult()
        ;
    }

    public function remove(WatchFileUser $watchFileUser): void
    {
        $this->entityManager->remove($watchFileUser);
        $this->entityManager->flush();
    }

    public function countByWatchFile(WatchFile $watchFile): int
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('COUNT(fu.id)')
            ->from(WatchFileUser::class, 'fu')
            ->where('fu.watchFile = :watchfile')
            ->setParameter('watchfile', $watchFile)
        ;

        return (int) $queryBuilder
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countByWatchFiles(iterable $watchFiles): array
    {
        $watchFileIds = [];
        foreach ($watchFiles as $watchFile) {
            $id = $watchFile->getId();
            if (!empty($id) && !\in_array($id, $watchFileIds, true)) {
                $watchFileIds[] = $id;
            }
        }

        if (0 === \count($watchFileIds)) {
            return [];
        }

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('COUNT(fu.id) as count, IDENTITY(fu.watchFile) as watchFileId')
            ->from(WatchFileUser::class, 'fu')
            ->where('fu.watchFile IN (:watchFileIds)')
            ->setParameter('watchFileIds', $watchFileIds)
            ->groupBy('fu.watchFile')
        ;

        /** @var array<int, array{count: int, watchFileId: string}> */
        $result = $queryBuilder
            ->getQuery()
            ->getArrayResult()
        ;

        if (empty($result)) {
            return [];
        }

        $formattedResult = [];

        foreach ($result as $row) {
            $formattedResult[$row['watchFileId']] = $row['count'];
        }

        return $formattedResult;
    }

    public function hasEditAccessForWatchFiles(User $user, iterable $watchFiles): array
    {
        $watchFileIds = [];
        $watchFileMap = [];

        foreach ($watchFiles as $watchFile) {
            $id = $watchFile->getId();
            if (!empty($id) && !\in_array($id, $watchFileIds, true)) {
                $watchFileIds[] = $id;
                $watchFileMap[$id] = $watchFile;
            }
        }

        if (0 === \count($watchFileIds)) {
            return [];
        }

        $result = [];

        // Then, check for WatchFileUser relationships with edit roles
        $qb = $this->entityManager->createQueryBuilder();

        $watchFileUserResults = $qb->select('IDENTITY(wu.watchFile) as watchFileId')
            ->from(WatchFileUser::class, 'wu')
            ->where('wu.user = :user')
            ->andWhere('IDENTITY(wu.watchFile) IN (:watchFileIds)')
            ->andWhere('wu.role IN (:editRoles)')
            ->setParameter('user', $user)
            ->setParameter('watchFileIds', $watchFileIds)
            ->setParameter('editRoles', [WatchFileUserRole::OWNER, WatchFileUserRole::EDITOR])
            ->getQuery()
            ->getResult();

        foreach ($watchFileUserResults as $row) {
            $result[$row['watchFileId']] = true;
        }

        return $result;
    }

    public function getUsersWithRealTimeAccess(WatchFile $watchFile): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $queryBuilder->select('u')
            ->from(User::class, 'u')
            ->innerJoin(WatchFileUser::class, 'wfu', Join::WITH, 'wfu.user = u AND wfu.watchFile = :watchFile')
            ->where('wfu.role IN (:roles)')
            ->setParameter('watchFile', $watchFile)
            ->setParameter('roles', WatchFileUserRole::ROLE_CAN_RECEIVE_REAL_TIME_UPDATES)
        ;

        return $queryBuilder
            ->getQuery()
            ->getResult()
        ;
    }
}
