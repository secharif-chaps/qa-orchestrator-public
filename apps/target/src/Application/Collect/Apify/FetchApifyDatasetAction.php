<?php

declare(strict_types=1);

namespace App\Application\Collect\Apify;

readonly class FetchApifyDatasetAction
{
    public function __construct(
        public string $collectTaskId,
        public string $datasetId,
    ) {
    }
}
