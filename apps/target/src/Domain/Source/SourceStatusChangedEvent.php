<?php

declare(strict_types=1);

namespace App\Domain\Source;

use App\Domain\User\User;
use App\Domain\WatchFile\Event\WatchFileRelatedEventInterface;
use App\Domain\WatchFile\WatchFile;

class SourceStatusChangedEvent implements WatchFileRelatedEventInterface
{
    public function __construct(
        public readonly Source $source,
        public readonly WatchFile $watchFile,
        public readonly User $user,
        public readonly SourceStatus $status,
        public readonly SourceStatus $oldStatus,
    ) {
    }

    public function getWatchFile(): WatchFile
    {
        return $this->watchFile;
    }
}
