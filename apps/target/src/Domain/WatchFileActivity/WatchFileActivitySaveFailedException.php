<?php

declare(strict_types=1);

namespace App\Domain\WatchFileActivity;

use App\Domain\Shared\DomainException;

class WatchFileActivitySaveFailedException extends DomainException
{
    public function __construct(
        string $message = 'Failed to save watchfile activity',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
