<?php

declare(strict_types=1);

namespace App\Tests\Integration\Collect;

use App\Domain\Collect\Exception\ProviderNotFoundException;
use App\Domain\Collect\ProviderGatewayInterface;
use App\Infrastructure\Collect\Bakus\BakusProviderGateway;
use App\Infrastructure\Collect\ProviderGatewayLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(ProviderGatewayLocator::class)]
class ProviderGatewayLocatorTest extends KernelTestCase
{
    public function testLocatorResolvesBakusProvider(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $locator = $container->get(ProviderGatewayLocator::class);
        $this->assertInstanceOf(ProviderGatewayLocator::class, $locator);

        $provider = $locator->get('bakus');

        $this->assertInstanceOf(ProviderGatewayInterface::class, $provider);
        $this->assertInstanceOf(BakusProviderGateway::class, $provider);
    }

    public function testLocatorHasBakusProvider(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $locator = $container->get(ProviderGatewayLocator::class);

        $this->assertTrue($locator->has('bakus'));
    }

    public function testLocatorDoesNotHaveUnknownProvider(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $locator = $container->get(ProviderGatewayLocator::class);

        $this->assertFalse($locator->has('unknown_provider'));
    }

    public function testLocatorThrowsForUnknownProvider(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $locator = $container->get(ProviderGatewayLocator::class);

        $this->expectException(ProviderNotFoundException::class);

        $locator->get('unknown_provider');
    }
}
