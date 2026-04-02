<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Shared;

use App\Domain\Shared\EntityEnricherInterface;
use App\Infrastructure\Shared\EntityEnricherLocator;
use PHPUnit\Framework\TestCase;

class EntityEnricherLocatorTest extends TestCase
{
    public function testGetEnrichersForReturnsEmptyArrayWhenNoEnrichersForClass(): void
    {
        $locator = new EntityEnricherLocator([]);

        $enrichers = $locator->getEnrichersFor(\stdClass::class);

        $this->assertSame([], $enrichers);
    }

    public function testGetEnrichersForReturnsEnrichersForDirectClass(): void
    {
        $enricher = $this->createMockEnricher(TestClass::class);
        $locator = new EntityEnricherLocator([$enricher]);

        $enrichers = $locator->getEnrichersFor(TestClass::class);

        $this->assertSame([$enricher], $enrichers);
    }

    public function testGetEnrichersForReturnsEnrichersForParentClass(): void
    {
        $enricher = $this->createMockEnricher(\stdClass::class);
        $locator = new EntityEnricherLocator([$enricher]);

        $enrichers = $locator->getEnrichersFor(TestChildClass::class);

        $this->assertSame([$enricher], $enrichers);
    }

    public function testGetEnrichersForEntity(): void
    {
        $enricher = $this->createMockEnricher(\stdClass::class);
        $locator = new EntityEnricherLocator([$enricher]);

        $entity = new \stdClass();
        $enrichers = $locator->getEnrichersForEntity($entity);

        $this->assertSame([$enricher], $enrichers);
    }

    public function testGetEnrichersForCachesResults(): void
    {
        $enricher = $this->createMockEnricher(TestClass::class);
        $locator = new EntityEnricherLocator([$enricher]);

        $enrichers1 = $locator->getEnrichersFor(TestClass::class);
        $enrichers2 = $locator->getEnrichersFor(TestClass::class);

        $this->assertSame($enrichers1, $enrichers2);
    }

    public function testGetEnrichersForHandlesMultipleEnrichersForSameClass(): void
    {
        $enricher1 = $this->createMockEnricher(TestClass::class);
        $enricher2 = $this->createMockEnricher(TestClass::class);
        $locator = new EntityEnricherLocator([$enricher1, $enricher2]);

        $enrichers = $locator->getEnrichersFor(TestClass::class);

        $this->assertCount(2, $enrichers);
        $this->assertContains($enricher1, $enrichers);
        $this->assertContains($enricher2, $enrichers);
    }

    public function testGetEnrichersForHandlesMixedDirectAndInheritedEnrichers(): void
    {
        $directEnricher = $this->createMockEnricher(TestChildClass::class);
        $parentEnricher = $this->createMockEnricher(\stdClass::class);
        $locator = new EntityEnricherLocator([$directEnricher, $parentEnricher]);

        $enrichers = $locator->getEnrichersFor(TestChildClass::class);

        $this->assertCount(2, $enrichers);
        $this->assertContains($directEnricher, $enrichers);
        $this->assertContains($parentEnricher, $enrichers);
    }

    /**
     * @return EntityEnricherInterface<object>
     */
    private function createMockEnricher(string $supportedClass): EntityEnricherInterface
    {
        $enricher = $this->createStub(EntityEnricherInterface::class);
        $enricher->method('supports')
            ->willReturn($supportedClass)
        ;

        return $enricher;
    }
}

class TestClass
{
}

class TestChildClass extends \stdClass
{
}
