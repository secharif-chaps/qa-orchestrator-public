<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\UsageLimit;

use App\Domain\UsageLimit\Exception\InvalidQuotaConfigException;
use App\Infrastructure\UsageLimit\Config\UsageLimitConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UsageLimitConfig::class)]
class UsageLimitConfigTest extends TestCase
{
    public function testReturnsConfiguredQuotaLimits(): void
    {
        $config = new UsageLimitConfig([
            'watchfile' => [
                'max_owned_non_archived' => 25,
                'max_active_per_user' => 2,
            ],
            'source' => [
                'max_per_watchfile' => null,
                'max_active_per_watchfile' => 20,
            ],
            'actor' => [
                'max_per_watchfile' => 75,
            ],
            'document' => [
                'max_per_watchfile' => 10000,
            ],
        ]);

        $this->assertSame(25, $config->watchFileMaxOwnedNonArchived()->value());
        $this->assertSame(2, $config->watchFileMaxActivePerUser()->value());
        $this->assertNull($config->sourceMaxPerWatchFile()->value());
        $this->assertSame(20, $config->sourceMaxActivePerWatchFile()->value());
        $this->assertSame(75, $config->actorMaxPerWatchFile()->value());
        $this->assertSame(10000, $config->documentMaxPerWatchFile()->value());
    }

    public function testThrowsWhenValueMissing(): void
    {
        $this->expectException(InvalidQuotaConfigException::class);
        $this->expectExceptionMessage(
            'Usage limit configuration "usage_limits.watchfile.max_owned_non_archived" is missing.'
        );

        $config = new UsageLimitConfig([
            'watchfile' => [
                'max_active_per_user' => 2,
            ],
        ]);

        $config->watchFileMaxOwnedNonArchived();
    }

    public function testThrowsWhenTypeInvalid(): void
    {
        $this->expectException(InvalidQuotaConfigException::class);
        $this->expectExceptionMessage(
            'Usage limit configuration "usage_limits.source.max_per_watchfile" must be an integer or null, string given.',
        );

        $config = new UsageLimitConfig([
            'source' => [
                'max_per_watchfile' => 'invalid',
            ],
        ]);

        $config->sourceMaxPerWatchFile();
    }

    public function testThrowsWhenValueIsNegative(): void
    {
        $this->expectException(InvalidQuotaConfigException::class);
        $this->expectExceptionMessage(
            'Usage limit configuration "usage_limits.watchfile.max_owned_non_archived" cannot be negative (given: -5).',
        );

        $config = new UsageLimitConfig([
            'watchfile' => [
                'max_owned_non_archived' => -5,
                'max_active_per_user' => 2,
            ],
        ]);

        $config->watchFileMaxOwnedNonArchived();
    }
}
