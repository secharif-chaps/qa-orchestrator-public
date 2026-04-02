<?php

declare(strict_types=1);

namespace App\Domain\Collect;

interface StatusMapperInterface
{
    public function mapStatus(string $providerStatus): CollectTaskStatus;
}
