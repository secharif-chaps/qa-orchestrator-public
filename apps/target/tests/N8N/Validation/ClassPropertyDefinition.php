<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

/**
 * Represents a PHP class property definition extracted via reflection.
 */
readonly class ClassPropertyDefinition
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
        public bool $hasDefault,
    ) {
    }
}
