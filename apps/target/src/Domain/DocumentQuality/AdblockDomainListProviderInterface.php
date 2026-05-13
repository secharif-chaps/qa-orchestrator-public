<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

interface AdblockDomainListProviderInterface
{
    /**
     * Returns match details when the domain is present in any loaded adblock list.
     * Returns null when the domain is clean (no match).
     */
    public function findMatch(string $domain): ?AdblockMatch;

    /**
     * Refreshes lists from configured sources and persists the consolidated set.
     * Implementations should be idempotent and safe to call from a scheduler.
     *
     * @return int number of unique domains loaded into the consolidated set
     */
    public function refresh(): int;
}
