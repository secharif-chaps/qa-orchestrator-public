<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFileActivity;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;
use App\Domain\WatchFileActivity\WatchFileActivityRetrievalFailedException;
use App\Domain\WatchFileActivity\WatchFileActivitySaveFailedException;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Psr\Log\LoggerInterface;

class WatchFileActivityDoctrineGateway implements WatchFileActivityGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function save(WatchFileActivity $watchFileActivity): void
    {
        try {
            $this->entityManager->persist($watchFileActivity);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger?->error('Failed to save watchfile activity', [
                'watch_file_id' => $watchFileActivity->getWatchFile()
->getId(),
                'action_type' => $watchFileActivity->getActionType()
->value,
            ]);
            throw new WatchFileActivitySaveFailedException('Failed to save watchfile activity', 0, $e);
        }
    }

    public function getByWatchFilePaginated(
        WatchFile $watchFile,
        int $page = 1,
        int $itemsPerPage = 30,
    ): PaginatorInterface {
        $qb = $this->getByWatchFileQueryBuilder($watchFile);
        $qb
            ->addCriteria(
                Criteria::create()
                    ->setFirstResult(($page - 1) * $itemsPerPage)
                    ->setMaxResults($itemsPerPage)
            );

        return new Paginator(new DoctrinePaginator($qb->getQuery()));
    }

    public function getByUserPaginated(User $user, int $page = 1, int $itemsPerPage = 30): PaginatorInterface
    {
        $qb = $this->getByUserQueryBuilder($user);
        $qb
            ->addCriteria(
                Criteria::create()
                    ->setFirstResult(($page - 1) * $itemsPerPage)
                    ->setMaxResults($itemsPerPage)
            );

        return new Paginator(new DoctrinePaginator($qb->getQuery()));
    }

    private function getByWatchFileQueryBuilder(WatchFile $watchFile): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('fa')
            ->from(WatchFileActivity::class, 'fa')
            ->where('fa.watchFile = :watchfile')
            ->setParameter('watchfile', $watchFile)
            ->orderBy('fa.createdAt', 'DESC');
    }

    private function getByUserQueryBuilder(User $user): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('fa')
            ->from(WatchFileActivity::class, 'fa')
            ->where('fa.user = :user')
            ->setParameter('user', $user)
            ->orderBy('fa.createdAt', 'DESC');
    }

    /**
     * Get activities for a watchfile grouped by day, with each day's activities sorted by createdAt descending.
     *
     * @return array{activitiesByDay: array<string, list<WatchFileActivity>>, hasNextPage: bool}
     */
    public function getByWatchFileGroupedByDay(WatchFile $watchFile, int $page = 1, int $itemsPerPage = 20): array
    {
        try {
            // Step 1: Get paginated days using QueryBuilder
            $daysQb = $this->entityManager->createQueryBuilder()
                ->select('DISTINCT DATE(fa.createdAt) as day')
                ->from(WatchFileActivity::class, 'fa')
                ->where('fa.watchFile = :watchFile')
                ->setParameter('watchFile', $watchFile)
                ->orderBy('DATE(fa.createdAt)', 'DESC')
                ->setFirstResult(($page - 1) * $itemsPerPage)
                ->setMaxResults($itemsPerPage);

            $days = $daysQb->getQuery()
                ->getSingleColumnResult();

            if (empty($days)) {
                return [
                    'activitiesByDay' => [],
                    'hasNextPage' => false,
                ];
            }

            // Step 1b: Check if has next page by looking for activities on the day before
            $lastDay = end($days);
            if (false === $lastDay || !\is_string($lastDay)) {
                $hasNextPage = false;
            } else {
                $dayBefore = new \DateTime($lastDay)
->modify('-1 day')
->format('Y-m-d');
                $hasNextPage = null !== $this->entityManager->createQueryBuilder()
                    ->select('1')
                    ->from(WatchFileActivity::class, 'fa')
                    ->where('fa.watchFile = :watchFile')
                    ->andWhere('DATE(fa.createdAt) = :dayBefore')
                    ->setParameter('watchFile', $watchFile)
                    ->setParameter('dayBefore', $dayBefore)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
            }

            // Step 2: Get activities only for these specific days
            $qb = $this->entityManager->createQueryBuilder()
                ->select('fa')
                ->from(WatchFileActivity::class, 'fa')
                ->where('fa.watchFile = :watchFile')
                ->andWhere('DATE(fa.createdAt) IN (:days)')
                ->setParameter('watchFile', $watchFile)
                ->setParameter('days', $days)
                ->orderBy('fa.createdAt', 'DESC');

            $activities = $qb->getQuery()
->getResult();

            // Step 3: Group activities by day
            $groupedActivities = [];
            foreach ($activities as $activity) {
                $day = $activity->getCreatedAt()
->format('Y-m-d');
                if (!isset($groupedActivities[$day])) {
                    $groupedActivities[$day] = [];
                }
                $groupedActivities[$day][] = $activity;
            }

            return [
                'activitiesByDay' => $groupedActivities,
                'hasNextPage' => $hasNextPage,
            ];
        } catch (\Exception $e) {
            $this->logger?->error('Failed to get watchfile activities grouped by day', [
                'watch_file_id' => $watchFile->getId(),
                'page' => $page,
                'items_per_page' => $itemsPerPage,
                'error' => $e->getMessage(),
            ]);
            throw new WatchFileActivityRetrievalFailedException(
                'Failed to get watchfile activities grouped by day',
                0,
                $e
            );
        }
    }
}
