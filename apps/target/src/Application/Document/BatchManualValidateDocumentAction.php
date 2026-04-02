<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Application\SyncActionInterface;
use App\Domain\Document\ManualValidationStatus;

readonly class BatchManualValidateDocumentAction implements SyncActionInterface
{
    /**
     * @param array<int, string> $documentIds
     */
    public function __construct(
        public array $documentIds,
        public ManualValidationStatus $action,
        public string $validatedByUserId,
    ) {
    }
}
