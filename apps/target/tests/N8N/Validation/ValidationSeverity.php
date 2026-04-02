<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

enum ValidationSeverity: string
{
    case CRITICAL = 'critical';
    case WARNING = 'warning';
}
