<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

interface AdblockListSourceInterface
{
    /**
     * Human-readable name of the source (e.g. "stevenblack", "easylist").
     */
    public function name(): string;

    /**
     * Fetches the source content (typically over HTTP) and yields parsed entries.
     * Implementations are expected to skip malformed lines silently and continue.
     *
     * @return iterable<AdblockListEntry>
     */
    public function fetchEntries(): iterable;
}
