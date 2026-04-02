<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\UsageLimit;

use App\Domain\UsageLimit\Exception\InvalidResourceCountException;
use App\Domain\UsageLimit\QuotaLimit;
use App\Domain\UsageLimit\ResourceCount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceCount::class)]
class ResourceCountTest extends TestCase
{
    public function testFromIntRejectsNegativeValue(): void
    {
        $this->expectException(InvalidResourceCountException::class);
        $this->expectExceptionMessage('Resource count cannot be negative (given: -5).');

        ResourceCount::fromInt(-5);
    }

    public function testValueReturnsProvidedCount(): void
    {
        $resourceCount = ResourceCount::fromInt(7);

        $this->assertSame(7, $resourceCount->value());
    }

    public function testExceedsReturnsFalseWhenQuotaUnlimited(): void
    {
        $resourceCount = ResourceCount::fromInt(100);
        $quotaLimit = QuotaLimit::fromNullableInt(null);

        $this->assertFalse($resourceCount->exceeds($quotaLimit));
    }

    public function testExceedsReturnsTrueWhenQuotaExceeded(): void
    {
        $resourceCount = ResourceCount::fromInt(11);
        $quotaLimit = QuotaLimit::fromNullableInt(10);

        $this->assertTrue($resourceCount->exceeds($quotaLimit));
    }
}
