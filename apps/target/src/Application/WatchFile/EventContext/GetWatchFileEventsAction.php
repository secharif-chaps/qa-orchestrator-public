<?php

declare(strict_types=1);

namespace App\Application\WatchFile\EventContext;

readonly class GetWatchFileEventsAction
{
    public const int DEFAULT_DAYS = 15;

    public function __construct(
        public string $watchFileId,
        public int $days = self::DEFAULT_DAYS,
    ) {
    }
}
