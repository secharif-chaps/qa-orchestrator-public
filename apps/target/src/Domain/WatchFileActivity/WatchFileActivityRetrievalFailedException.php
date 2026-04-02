<?php

declare(strict_types=1);

namespace App\Domain\WatchFileActivity;

use App\Domain\Shared\DomainException;

class WatchFileActivityRetrievalFailedException extends DomainException
{
    public function __construct(
        string $message = 'Failed to retrieve watch file activities',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
