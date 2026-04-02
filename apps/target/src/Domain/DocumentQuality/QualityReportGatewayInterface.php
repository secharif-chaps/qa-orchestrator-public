<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

interface QualityReportGatewayInterface
{
    public function save(QualityReport $report): string;

    public function findByDocumentId(string $documentId): QualityReport;
}
