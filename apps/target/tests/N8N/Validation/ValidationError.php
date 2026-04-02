<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

readonly class ValidationError
{
    public function __construct(
        public string $workflowFile,
        public string $workflowName,
        public string $nodeName,
        public string $nodeId,
        public string $errorType,
        public string $message,
        public ValidationSeverity $severity = ValidationSeverity::CRITICAL,
    ) {
    }

    public function isCritical(): bool
    {
        return ValidationSeverity::CRITICAL === $this->severity;
    }

    public function isWarning(): bool
    {
        return ValidationSeverity::WARNING === $this->severity;
    }
}
