<?php

namespace App\Domain\WatchFile\Event;

use App\Domain\WatchFile\WatchFile;

/**
 * Interface for events that are related to a WatchFile.
 * Used to automatically trigger side effects like cache invalidation.
 */
interface WatchFileRelatedEventInterface
{
    public function getWatchFile(): WatchFile;
}
