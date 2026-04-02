<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect;

use App\Domain\Collect\ProviderResolverInterface;
use App\Domain\Source\Source;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class SourceTypeProviderResolver implements ProviderResolverInterface
{
    /**
     * @param array<string, string> $sourceTypeOverrides
     */
    public function __construct(
        #[Autowire('%app.collect.provider_routing.default%')]
        private string $defaultProvider,
        #[Autowire('%app.collect.provider_routing.source_types%')]
        private array $sourceTypeOverrides,
    ) {
    }

    public function resolve(Source $source): string
    {
        return $this->sourceTypeOverrides[$source->getType()->value] ?? $this->defaultProvider;
    }
}
