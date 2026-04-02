<?php

declare(strict_types=1);

namespace App\Application\WatchFile\SearchResult;

use App\Application\SyncActionInterface;

readonly class ExtractSearchResultEntitiesAction implements SyncActionInterface
{
    public function __construct(
        public string $searchResultId,
        /**
         * @var array<int, array<string, mixed>>
         */
        public array $extractions,
    ) {
    }
}
