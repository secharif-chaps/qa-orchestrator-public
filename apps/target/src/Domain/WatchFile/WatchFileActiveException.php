<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use App\Domain\Shared\AccessDeniedException;

/**
 * Exception thrown when trying to modify a watch file that is in active status.
 * Active watch files have their configuration locked and cannot be modified.
 */
class WatchFileActiveException extends AccessDeniedException
{
    public function __construct(string $watchFileId, string $operation)
    {
        parent::__construct(
            \sprintf(
                'Cannot %s on watch file %s because it is in active status. Please set the watch file to draft mode first.',
                $operation,
                $watchFileId
            )
        );
    }
}
