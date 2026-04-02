<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\WatchFile\WatchFile;

/**
 * Interface for models that have a relationship with a WatchFile.
 * This interface defines the contract for models that can return their associated WatchFile.
 */
interface HasWatchFileInterface
{
    /**
     * Returns the associated WatchFile for this model.
     *
     * @return WatchFile|null The associated WatchFile, or null if not set
     */
    public function getWatchFile(): ?WatchFile;
}
