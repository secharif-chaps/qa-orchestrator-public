<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

interface TenantAwareInterface
{
    public function getOrganisation(): Organisation;
}
