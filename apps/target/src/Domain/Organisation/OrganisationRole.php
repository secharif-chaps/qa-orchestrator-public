<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

enum OrganisationRole: string
{
    case ADMIN = 'admin';
    case MEMBER = 'member';
    case VIEWER = 'viewer';
}
