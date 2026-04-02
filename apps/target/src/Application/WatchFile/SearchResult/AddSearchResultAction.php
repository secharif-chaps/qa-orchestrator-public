<?php

declare(strict_types=1);

namespace App\Application\WatchFile\SearchResult;

use App\Application\SyncActionInterface;

readonly class AddSearchResultAction implements SyncActionInterface
{
    public function __construct(
        public string $searchQueryId,
        public string $title,
        public string $description,
        public string $url,
        public string $content = '',
    ) {
    }
}
