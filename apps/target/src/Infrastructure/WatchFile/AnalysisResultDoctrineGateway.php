<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\WatchFile\AnalysisResult;
use App\Domain\WatchFile\AnalysisResultGatewayInterface;
use App\Domain\WatchFile\WatchFile;
use Doctrine\ORM\EntityManagerInterface;

class AnalysisResultDoctrineGateway implements AnalysisResultGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(AnalysisResult $analysisResult): void
    {
        $this->entityManager->persist($analysisResult);
        $this->entityManager->flush();
    }

    public function getLastOrCreate(WatchFile $watchFile): AnalysisResult
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('ar')
            ->from(AnalysisResult::class, 'ar')
            ->where('ar.watchFile = :watchFile')
            ->setParameter('watchFile', $watchFile)
            ->orderBy('ar.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery();

        /** @var AnalysisResult|null $analysisResult */
        $analysisResult = $query->getOneOrNullResult();
        if (null === $analysisResult) {
            return new AnalysisResult($watchFile, 'WatchFile Type Classification');
        }

        return $analysisResult;
    }
}
