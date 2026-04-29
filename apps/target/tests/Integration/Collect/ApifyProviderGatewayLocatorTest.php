<?php

declare(strict_types=1);

namespace App\Tests\Integration\Collect;

use App\Domain\Collect\ProviderGatewayInterface;
use App\Domain\Collect\ProviderGatewayLocatorInterface;
use App\Domain\Collect\ProviderResolverInterface;
use App\Domain\Source\SourceType;
use App\Infrastructure\Collect\Apify\ApifyProviderGateway;
use App\Infrastructure\Collect\ProviderGatewayLocator;
use App\Infrastructure\Collect\SourceTypeProviderResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(ApifyProviderGateway::class)]
#[CoversClass(ProviderGatewayLocator::class)]
#[CoversClass(SourceTypeProviderResolver::class)]
class ApifyProviderGatewayLocatorTest extends KernelTestCase
{
    public function testLocatorResolvesApifyProvider(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $locator = $container->get(ProviderGatewayLocator::class);
        $this->assertInstanceOf(ProviderGatewayLocator::class, $locator);

        $provider = $locator->get('apify');

        $this->assertInstanceOf(ProviderGatewayInterface::class, $provider);
        $this->assertInstanceOf(ApifyProviderGateway::class, $provider);
    }

    public function testLocatorHasApifyProvider(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $locator = $container->get(ProviderGatewayLocator::class);

        $this->assertTrue($locator->has('apify'));
    }

    public function testLocatorHasBothProvidersRegistered(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $locator = $container->get(ProviderGatewayLocator::class);

        $this->assertTrue($locator->has('bakus'), 'The bakus provider should still be registered');
        $this->assertTrue($locator->has('apify'), 'The apify provider should be registered');
    }

    public function testConfigurableProviderResolverRoutesWebsiteToApify(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $resolver = $container->get(ProviderResolverInterface::class);
        $this->assertInstanceOf(SourceTypeProviderResolver::class, $resolver);

        /** @var array<string, string> $routing */
        $routing = $container->getParameter('app.collect.provider_routing.source_types');

        $this->assertSame(
            'apify',
            $routing[SourceType::WEBSITE->value],
            'SourceType::WEBSITE should be routed to apify in the source_types routing config',
        );
    }

    public function testBakusProviderStillResolvesForBakusRoutedTypes(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var array<string, string> $routing */
        $routing = $container->getParameter('app.collect.provider_routing.source_types');

        // At least one SourceType must still be routed to bakus (or use the default bakus provider).
        // If every type is overridden to apify, this test catches a misconfiguration.
        $locator = $container->get(ProviderGatewayLocatorInterface::class);

        $bakusRoutedTypes = array_keys(array_filter(
            $routing,
            static fn (string $provider) => 'bakus' === $provider,
        ));

        // Default provider should also be bakus
        $defaultProvider = $container->getParameter('app.collect.provider_routing.default');

        $hasBakusRouting = [] !== $bakusRoutedTypes || 'bakus' === $defaultProvider;

        $this->assertTrue(
            $hasBakusRouting,
            'At least one SourceType (or the default provider) should still route to bakus',
        );

        // The bakus provider itself must remain resolvable in the locator
        $this->assertTrue($locator->has('bakus'), 'bakus provider must remain available in the locator');
    }
}
