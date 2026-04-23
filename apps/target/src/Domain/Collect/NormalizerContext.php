<?php

declare(strict_types=1);

namespace App\Domain\Collect;

readonly class NormalizerContext
{
    public function __construct(
        public ?string $actorType,
        public string $datasetId,
        public int $itemIndex,
    ) {
    }
}
