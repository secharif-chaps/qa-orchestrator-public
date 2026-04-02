<?php

declare(strict_types=1);

namespace App\Application\WatchFileActivity;

readonly class GetWatchFileHistoryAction
{
    public function __construct(
        public string $watchFileId,
    ) {
    }
}
