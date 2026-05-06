<?php

declare(strict_types=1);

namespace App\Infrastructure\User;

use App\Domain\User\User;
use App\Domain\User\UserFavoriteWatchFile;
use App\Domain\User\UserFavoriteWatchFileGatewayInterface;
use App\Domain\User\UserFavoriteWatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;
use Doctrine\ORM\EntityManagerInterface;

class UserFavoriteWatchFileDoctrineGateway implements UserFavoriteWatchFileGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getByUserAndWatchFile(User $user, WatchFile $watchFile): UserFavoriteWatchFile
    {
        $userFavoriteWatchFiles = $this->getByUserAndWatchFiles($user, [$watchFile]);
        $userFavoriteWatchFile = $userFavoriteWatchFiles[0] ?? null;

        if (!$userFavoriteWatchFile instanceof UserFavoriteWatchFile) {
            throw new UserFavoriteWatchFileNotFoundException(\sprintf(
                'User favorite watchfile not found for user %s and watchfile %s',
                $user->getId(),
                $watchFile->getId()
            ));
        }

        return $userFavoriteWatchFile;
    }

    public function getByUserAndWatchFiles(User $user, iterable $watchFiles): array
    {
        $qb = $this->entityManager->createQueryBuilder();

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

        $qb->select('uf')
            ->from(UserFavoriteWatchFile::class, 'uf')
            ->where('IDENTITY(uf.user) = :user')
            ->andWhere('IDENTITY(uf.watchFile) IN (:watchFileIds)')
            ->setParameter('user', $user)
            ->setParameter('watchFileIds', $watchFileIds)
        ;

        return $qb->getQuery()
            ->getResult()
        ;
    }

    public function save(UserFavoriteWatchFile $userFavoriteWatchFile): void
    {
        $this->entityManager->persist($userFavoriteWatchFile);
        $this->entityManager->flush();
    }

    public function remove(UserFavoriteWatchFile $userFavoriteWatchFile): void
    {
        $this->entityManager->remove($userFavoriteWatchFile);
        $this->entityManager->flush();
    }
}
