<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

readonly class AdblockListEntry
{
    public function __construct(
        public string $domain,
        public AdblockListCategory $category,
    ) {
    }
}
