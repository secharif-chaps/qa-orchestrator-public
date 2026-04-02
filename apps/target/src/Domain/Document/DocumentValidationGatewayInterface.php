<?php

declare(strict_types=1);

namespace App\Domain\Document;

interface DocumentValidationGatewayInterface
{
    public function save(DocumentValidation $documentValidation): void;

    /**
     * Save multiple document validations using batch operation.
     *
     * @param array<int, DocumentValidation> $documentValidations
     */
    public function saveBulk(array $documentValidations): void;
}
