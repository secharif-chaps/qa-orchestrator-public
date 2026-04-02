<?php

declare(strict_types=1);

namespace App\Domain\Document\Exception;

use App\Domain\Shared\DomainException;

class InvalidSummaryStateException extends DomainException
{
    public function __construct(
        string $message = 'Invalid summary state: both summary and error provided',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
