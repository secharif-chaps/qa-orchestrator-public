<?php

declare(strict_types=1);

namespace App\Application\Collect\Apify;

use App\Application\SyncActionInterface;
use App\Domain\Collect\ApifyRunCost;

readonly class FetchApifyDatasetAction implements SyncActionInterface
{
    public function __construct(
        public string $collectTaskId,
        public string $datasetId,
        public ?ApifyRunCost $runCost = null,
    ) {
    }
}
