<?php

declare(strict_types=1);

namespace App\Application\WatchFile\SearchQuery;

use App\Application\SyncActionInterface;

readonly class AddSearchQueryAction implements SyncActionInterface
{
    public function __construct(
        public string $strategicQuestionId,
        public string $country,
        public string $language,
        public string $query,
        public string $queryType,
        public string $rationale,
    ) {
    }
}
