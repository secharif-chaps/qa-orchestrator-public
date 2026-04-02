<?php

namespace App\Domain\Collect\Stream\Exception;

use App\Domain\Shared\DomainException;

class AbnormallyCloseStreamException extends DomainException
{
    public function __construct(
        public readonly int $attempts,
        public readonly bool $willRetry,
        public readonly string $codeMeaning,
        string $message,
        int $code,
    ) {
        parent::__construct($message, $code);
    }
}
