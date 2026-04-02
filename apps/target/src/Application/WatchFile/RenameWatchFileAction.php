<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

readonly class RenameWatchFileAction
{
    public function __construct(
        public string $watchFileId,
        public string $name,
        public bool $isManualRename = false,
    ) {
    }
}
