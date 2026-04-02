<?php

namespace App\Domain\Collect\Stream\Exception;

use App\Domain\Shared\DomainException;

class ErrorClientStreamException extends DomainException
{
    public function __construct(
        public readonly int $attempts,
        public readonly bool $willRetry,
        string $message,
        int $code,
        \Throwable $previous,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
