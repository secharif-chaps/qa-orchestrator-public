<?php

declare(strict_types=1);

namespace App\Domain\Document\Exception;

use App\Domain\Shared\DomainException;

class ValidationException extends DomainException
{
    public static function validationFailed(string $message, ?\Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }
}
