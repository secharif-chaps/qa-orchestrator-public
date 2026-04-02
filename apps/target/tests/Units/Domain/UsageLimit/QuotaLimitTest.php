<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\UsageLimit;

use App\Domain\UsageLimit\Exception\InvalidQuotaLimitException;
use App\Domain\UsageLimit\QuotaLimit;
use App\Domain\UsageLimit\ResourceCount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuotaLimit::class)]
class QuotaLimitTest extends TestCase
{
    public function testFromNullableIntAcceptsNull(): void
    {
        $quotaLimit = QuotaLimit::fromNullableInt(null);

        $this->assertTrue($quotaLimit->isUnlimited());
        $this->assertNull($quotaLimit->value());
    }

    public function testFromNullableIntRejectsNegativeValue(): void
    {
        $this->expectException(InvalidQuotaLimitException::class);
        $this->expectExceptionMessage('Quota limit cannot be negative (given: -1).');

        QuotaLimit::fromNullableInt(-1);
    }

    public function testAllowsResourceCountWithinLimit(): void
    {
        $quotaLimit = QuotaLimit::fromNullableInt(5);
        $resourceCount = ResourceCount::fromInt(4);

        $this->assertTrue($quotaLimit->allows($resourceCount));
        $this->assertFalse($quotaLimit->requiresLimitEnforcement($resourceCount));
    }

    public function testUnlimitedQuotaAlwaysAllows(): void
    {
        $quotaLimit = QuotaLimit::fromNullableInt(null);
        $resourceCount = ResourceCount::fromInt(50);

        $this->assertTrue($quotaLimit->allows($resourceCount));
        $this->assertFalse($quotaLimit->requiresLimitEnforcement($resourceCount));
    }

    public function testRequiresLimitEnforcementWhenExceeded(): void
    {
        $quotaLimit = QuotaLimit::fromNullableInt(10);
        $resourceCount = ResourceCount::fromInt(11);

        $this->assertFalse($quotaLimit->allows($resourceCount));
        $this->assertTrue($quotaLimit->requiresLimitEnforcement($resourceCount));
    }

    public function testExclusiveLimitRejectsExactValue(): void
    {
        $quotaLimit = QuotaLimit::fromNullableInt(10, inclusive: false);
        $resourceCount = ResourceCount::fromInt(10);

        $this->assertFalse($quotaLimit->allows($resourceCount));
        $this->assertTrue($quotaLimit->requiresLimitEnforcement($resourceCount));
    }

    public function testExclusiveLimitAllowsBelowValue(): void
    {
        $quotaLimit = QuotaLimit::fromNullableInt(10, inclusive: false);
        $resourceCount = ResourceCount::fromInt(9);

        $this->assertTrue($quotaLimit->allows($resourceCount));
        $this->assertFalse($quotaLimit->requiresLimitEnforcement($resourceCount));
    }

    public function testInclusiveLimitAllowsExactValue(): void
    {
        $quotaLimit = QuotaLimit::fromNullableInt(10, inclusive: true);
        $resourceCount = ResourceCount::fromInt(10);

        $this->assertTrue($quotaLimit->allows($resourceCount));
        $this->assertFalse($quotaLimit->requiresLimitEnforcement($resourceCount));
    }

    public function testInclusiveLimitRejectsAboveValue(): void
    {
        $quotaLimit = QuotaLimit::fromNullableInt(10, inclusive: true);
        $resourceCount = ResourceCount::fromInt(11);

        $this->assertFalse($quotaLimit->allows($resourceCount));
        $this->assertTrue($quotaLimit->requiresLimitEnforcement($resourceCount));
    }
}
