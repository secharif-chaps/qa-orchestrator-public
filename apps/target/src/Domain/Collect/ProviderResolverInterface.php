<?php

declare(strict_types=1);

namespace App\Domain\Collect;

use App\Domain\Source\Source;

interface ProviderResolverInterface
{
    public function resolve(Source $source): string;
}
