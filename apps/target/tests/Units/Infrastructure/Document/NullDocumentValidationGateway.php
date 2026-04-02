<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document;

use App\Domain\Document\DocumentValidation;
use App\Domain\Document\DocumentValidationGatewayInterface;

class NullDocumentValidationGateway implements DocumentValidationGatewayInterface
{
    /**
     * @var array<int, DocumentValidation>
     */
    private array $validations = [];

    public function save(DocumentValidation $documentValidation): void
    {
        $this->validations[] = $documentValidation;
    }

    /**
     * @param array<int, DocumentValidation> $documentValidations
     */
    public function saveBulk(array $documentValidations): void
    {
        foreach ($documentValidations as $documentValidation) {
            $this->validations[] = $documentValidation;
        }
    }

    /**
     * @return array<int, DocumentValidation>
     */
    public function getAll(): array
    {
        return $this->validations;
    }

    public function clear(): void
    {
        $this->validations = [];
    }

    public function count(): int
    {
        return \count($this->validations);
    }
}
