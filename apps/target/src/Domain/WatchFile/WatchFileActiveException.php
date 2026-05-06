<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use App\Domain\Shared\AccessDeniedException;

/**
 * Exception thrown when trying to modify a watchfile that is in active status.
 * Active watchfiles have their configuration locked and cannot be modified.
 */
class WatchFileActiveException extends AccessDeniedException
{
    public function __construct(string $watchFileId, string $operation)
    {
        parent::__construct(
            \sprintf(
                'Cannot %s on watchfile %s because it is in active status. Please set the watchfile to draft mode first.',
                $operation,
                $watchFileId
            )
        );
    }
}
