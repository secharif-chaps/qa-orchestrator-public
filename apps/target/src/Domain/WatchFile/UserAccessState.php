<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

enum UserAccessState: string
{
    case NO_ACCESS = 'no_access';
    case EDITOR = 'editor';
    case VIEWER = 'viewer';
    case ADMIN = 'admin';
}
