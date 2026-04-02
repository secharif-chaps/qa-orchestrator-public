<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Domain\Document\AIValidation;
use App\Domain\Document\AiValidationStatus;

readonly class EnrichDocumentWithAiValidationAction
{
    public AiValidationStatus $aiValidationStatus;

    public function __construct(
        public string $documentId,
        public ?AIValidation $aiValidation = null,
        public ?string $validationError = null,
    ) {
        $this->aiValidationStatus = $this->resolveAiValidationStatus();
    }

    private function resolveAiValidationStatus(): AiValidationStatus
    {
        if (null !== $this->validationError) {
            return AiValidationStatus::FAILED;
        }

        return AiValidationStatus::PENDING;
    }
}
