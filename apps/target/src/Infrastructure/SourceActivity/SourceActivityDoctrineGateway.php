<?php

declare(strict_types=1);

namespace App\Infrastructure\SourceActivity;

use App\Domain\Source\Source;
use App\Domain\SourceActivity\SourceActivity;
use App\Domain\SourceActivity\SourceActivityActionType;
use App\Domain\SourceActivity\SourceActivityGatewayInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SourceActivityDoctrineGateway implements SourceActivityGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function save(SourceActivity $sourceActivity): void
    {
        try {
            $this->entityManager->persist($sourceActivity);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger?->error('Failed to save source activity', [
                'source_id' => $sourceActivity->getSource()
                                                ->getId(),
                'user_id' => $sourceActivity->getUser()?->getId(),
                'action_type' => $sourceActivity->getActionType()
                                                ->value,
            ]);
            throw $e;
        }
    }

    /**
     * Get activities for a source grouped by day, with each day's activities sorted by createdAt descending.
     *
     * @return array<string, array<SourceActivity>>
     */
    public function getBySourceGroupedByDay(Source $source, int $page = 1, int $itemsPerPage = 20): array
    {
        try {
            // First, get all activities for the source, ordered by createdAt DESC
            $qb = $this->entityManager->createQueryBuilder()
                ->select('sa')
                ->from(SourceActivity::class, 'sa')
                ->where('sa.source = :source')
                ->andWhere('sa.actionType NOT IN (:excludedActionTypes)')
                ->setParameter('source', $source)
                ->setParameter('excludedActionTypes', [
                    SourceActivityActionType::SOURCE_QUERY_LOG,
                    SourceActivityActionType::SOURCE_COLLECT_LOG,
                ])
                ->setFirstResult(($page - 1) * $itemsPerPage)
                ->setMaxResults($itemsPerPage)
                ->orderBy('sa.createdAt', 'DESC');

            $allActivities = $qb->getQuery()
->getResult();

            // Group activities by day
            $groupedActivities = [];
            foreach ($allActivities as $activity) {
                $day = $activity->getCreatedAt()
->format('Y-m-d');
                $groupedActivities[$day][] = $activity;
            }

            return $groupedActivities;
        } catch (\Exception $e) {
            $this->logger?->error('Failed to get source activities grouped by day', [
                'source_id' => $source->getId(),
                'page' => $page,
                'items_per_page' => $itemsPerPage,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
