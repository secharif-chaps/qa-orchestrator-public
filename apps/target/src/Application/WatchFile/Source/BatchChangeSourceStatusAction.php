<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Source;

use App\Application\SyncActionInterface;
use App\Domain\Source\SourceStatus;
use App\UserInterface\Dto\Source\BatchChangeSourceDataDto;

class BatchChangeSourceStatusAction implements SyncActionInterface
{
    /**
     * @param BatchChangeSourceDataDto[] $sources
     */
    public function __construct(
        public readonly array $sources,
        public readonly SourceStatus $status,
    ) {
    }
}
