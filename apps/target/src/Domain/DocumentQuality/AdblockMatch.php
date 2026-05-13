<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

readonly class AdblockMatch
{
    public function __construct(
        public string $domain,
        public AdblockListCategory $category,
        public string $sourceName,
    ) {
    }
}
