<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\DocumentQuality\Stub;

use App\Domain\DocumentQuality\AdblockListCategory;
use App\Domain\DocumentQuality\AdblockListEntry;
use App\Domain\DocumentQuality\AdblockListSourceInterface;
use App\Domain\DocumentQuality\Exception\AdblockSourceUnavailableException;

/**
 * Hand-rolled in-memory source for testing consolidation logic.
 */
class InMemoryAdblockListSource implements AdblockListSourceInterface
{
    /** @var list<AdblockListEntry> */
    private array $entries = [];
    private bool $shouldFail = false;

    public function __construct(
        private readonly string $name,
    ) {
    }

    public function withEntry(string $domain, AdblockListCategory $category = AdblockListCategory::ADS): self
    {
        $this->entries[] = new AdblockListEntry($domain, $category);

        return $this;
    }

    public function failOnFetch(): self
    {
        $this->shouldFail = true;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function fetchEntries(): iterable
    {
        if ($this->shouldFail) {
            throw new AdblockSourceUnavailableException($this->name);
        }

        yield from $this->entries;
    }
}
