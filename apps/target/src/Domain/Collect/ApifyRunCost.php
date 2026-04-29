<?php

declare(strict_types=1);

namespace App\Domain\Collect;

readonly class ApifyRunCost
{
    public function __construct(
        public float $computeUnits,
        public float $costUsd,
        public ?int $durationSeconds = null,
    ) {
    }
}
