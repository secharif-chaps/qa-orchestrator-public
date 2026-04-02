<?php

declare(strict_types=1);

namespace App\Domain\Document;

enum DocumentStatus: string
{
    case PENDING = 'pending';
    case VALIDATED = 'validated';
    case REJECTED = 'rejected';
}
