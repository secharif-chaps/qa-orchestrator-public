<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Workflow\SearchQuery;

readonly class FilterUrlAction
{
    public function __construct(
        public string $watchFileId,
    ) {
    }
}
