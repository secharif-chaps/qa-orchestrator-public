<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

enum MembershipType: string
{
    case MANAGED = 'managed';
    case UNMANAGED = 'unmanaged';
}
