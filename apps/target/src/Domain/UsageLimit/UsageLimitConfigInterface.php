<?php

declare(strict_types=1);

namespace App\Domain\UsageLimit;

interface UsageLimitConfigInterface
{
    public function watchFileMaxOwnedNonArchived(): QuotaLimit;

    public function watchFileMaxActivePerUser(): QuotaLimit;

    public function sourceMaxPerWatchFile(): QuotaLimit;

    public function sourceMaxActivePerWatchFile(): QuotaLimit;

    public function actorMaxPerWatchFile(): QuotaLimit;

    public function documentMaxPerWatchFile(): QuotaLimit;
}
