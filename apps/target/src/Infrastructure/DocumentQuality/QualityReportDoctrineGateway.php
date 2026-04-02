<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentQuality;

use App\Domain\DocumentQuality\Exception\QualityReportNotFoundException;
use App\Domain\DocumentQuality\QualityReport;
use App\Domain\DocumentQuality\QualityReportGatewayInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class QualityReportDoctrineGateway implements QualityReportGatewayInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(QualityReport $report): string
    {
        $existing = $this->entityManager->getRepository(QualityReport::class)
            ->findOneBy([
                'documentId' => $report->documentId,
            ]);

        if (null !== $existing) {
            $existing->overallScore = $report->overallScore;
            $existing->categoryScores = $report->categoryScores;
            $existing->signals = $report->signals;
            $existing->decision = $report->decision;
            $existing->decisionReason = $report->decisionReason;
            $existing->computedAt = $report->computedAt;
            $this->entityManager->flush();

            return $existing->id;
        }

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return $report->id;
    }

    public function findByDocumentId(string $documentId): QualityReport
    {
        $report = $this->entityManager->getRepository(QualityReport::class)
            ->findOneBy([
                'documentId' => $documentId,
            ]);

        if (null === $report) {
            throw new QualityReportNotFoundException(\sprintf(
                'QualityReport for document "%s" not found.',
                $documentId
            ));
        }

        return $report;
    }
}
