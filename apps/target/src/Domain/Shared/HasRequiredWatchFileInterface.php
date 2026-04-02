<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\WatchFile\WatchFile;

/**
 * Interface for models that have a required (non-nullable) relationship with a WatchFile.
 * This interface defines the contract for models that can return their associated WatchFile.
 */
interface HasRequiredWatchFileInterface extends HasWatchFileInterface
{
    /**
     * Returns the associated WatchFile for this model.
     *
     * @return WatchFile The associated WatchFile (never null)
     */
    public function getWatchFile(): WatchFile;
}
