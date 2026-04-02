<?php

declare(strict_types=1);

namespace App\Domain\UsageLimit\Exception;

use App\Domain\Shared\DomainException;

class InvalidQuotaLimitException extends DomainException
{
    public static function negativeValue(int $value): self
    {
        return new self(\sprintf('Quota limit cannot be negative (given: %d).', $value));
    }
}
