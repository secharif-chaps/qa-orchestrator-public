<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

enum WatchFileStatus: string
{
    case DRAFT = 'draft';
    case ENABLED = 'enabled';
    case ARCHIVED = 'archived';
}
