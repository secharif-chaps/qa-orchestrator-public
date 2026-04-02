<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect;

use App\Domain\Collect\Exception\ProviderNotFoundException;
use App\Domain\Collect\ProviderGatewayInterface;
use App\Domain\Collect\ProviderGatewayLocatorInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ProviderGatewayLocator implements ProviderGatewayLocatorInterface
{
    /**
     * @param ServiceLocator<ProviderGatewayInterface> $providerLocator
     */
    public function __construct(
        private readonly ServiceLocator $providerLocator,
    ) {
    }

    public function get(string $providerName): ProviderGatewayInterface
    {
        if (!$this->providerLocator->has($providerName)) {
            throw ProviderNotFoundException::withName($providerName);
        }

        return $this->providerLocator->get($providerName);
    }

    public function has(string $providerName): bool
    {
        return $this->providerLocator->has($providerName);
    }
}
