<?php

declare(strict_types=1);

namespace App\Domain\Document;

enum ExtractionStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
