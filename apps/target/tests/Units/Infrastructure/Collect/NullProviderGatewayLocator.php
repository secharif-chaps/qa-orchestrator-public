<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect;

use App\Domain\Collect\Exception\ProviderNotFoundException;
use App\Domain\Collect\ProviderGatewayInterface;
use App\Domain\Collect\ProviderGatewayLocatorInterface;

class NullProviderGatewayLocator implements ProviderGatewayLocatorInterface
{
    /**
     * @param list<string> $knownProviders
     */
    public function __construct(
        private readonly array $knownProviders = ['bakus'],
    ) {
    }

    public function get(string $providerName): ProviderGatewayInterface
    {
        if (!$this->has($providerName)) {
            throw ProviderNotFoundException::withName($providerName);
        }

        return new NullProviderGateway();
    }

    public function has(string $providerName): bool
    {
        return \in_array($providerName, $this->knownProviders, true);
    }
}
