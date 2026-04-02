<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Application\SyncActionInterface;

readonly class GetRecentEventsContextAction implements SyncActionInterface
{
    public const int DEFAULT_DAYS_BACK = 15;

    public function __construct(
        public string $watchFileId,
        public int $daysBack = self::DEFAULT_DAYS_BACK,
    ) {
    }
}
