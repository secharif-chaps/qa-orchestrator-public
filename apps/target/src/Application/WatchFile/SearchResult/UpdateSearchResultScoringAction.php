<?php

declare(strict_types=1);

namespace App\Application\WatchFile\SearchResult;

use App\Application\SyncActionInterface;

readonly class UpdateSearchResultScoringAction implements SyncActionInterface
{
    public function __construct(
        public string $searchResultId,
        public ?int $qualityScore = null,
        public ?bool $isSelected = null,
        public ?int $selectionRank = null,
    ) {
    }
}
