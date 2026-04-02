<?php

declare(strict_types=1);

namespace App\Domain\Document;

enum AiValidationStatus: string
{
    case PENDING = 'pending';
    case VALIDATED = 'validated';
    case REJECTED = 'rejected';
    case UNCERTAIN = 'uncertain';
    case FAILED = 'failed';
}
