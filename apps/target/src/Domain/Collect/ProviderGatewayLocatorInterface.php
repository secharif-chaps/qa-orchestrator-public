<?php

declare(strict_types=1);

namespace App\Domain\Collect;

interface ProviderGatewayLocatorInterface
{
    public function get(string $providerName): ProviderGatewayInterface;

    public function has(string $providerName): bool;
}
