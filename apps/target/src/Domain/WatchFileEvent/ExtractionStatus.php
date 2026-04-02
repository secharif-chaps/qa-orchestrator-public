<?php

declare(strict_types=1);

namespace App\Domain\WatchFileEvent;

enum ExtractionStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
}
