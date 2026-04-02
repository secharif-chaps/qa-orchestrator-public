<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Application\SyncActionInterface;
use App\Domain\Document\ManualValidationStatus;

readonly class ManualValidateDocumentAction implements SyncActionInterface
{
    public function __construct(
        public string $documentId,
        public ManualValidationStatus $action,
        public string $validatedByUserId,
    ) {
    }
}
