<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

readonly class ValidationResult
{
    /**
     * @param ValidationError[] $errors
     */
    public function __construct(
        public string $validatorName,
        public array $errors,
        public int $totalWorkflows,
        public int $totalNodes,
    ) {
    }

    /**
     * @return ValidationError[]
     */
    public function getCriticalErrors(): array
    {
        return array_filter($this->errors, fn (ValidationError $e) => $e->isCritical());
    }

    /**
     * @return ValidationError[]
     */
    public function getWarnings(): array
    {
        return array_filter($this->errors, fn (ValidationError $e) => $e->isWarning());
    }

    public function hasCriticalErrors(): bool
    {
        return \count($this->getCriticalErrors()) > 0;
    }

    public function isValid(): bool
    {
        return !$this->hasCriticalErrors();
    }
}
