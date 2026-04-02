<?php

declare(strict_types=1);

namespace App\Domain\UsageLimit\Exception;

use App\Domain\Shared\DomainException;

class InvalidQuotaConfigException extends DomainException
{
    public static function missing(string $path): self
    {
        return new self(\sprintf('Usage limit configuration "%s" is missing.', $path));
    }

    public static function invalidType(string $path, mixed $value): self
    {
        return new self(
            \sprintf(
                'Usage limit configuration "%s" must be an integer or null, %s given.',
                $path,
                get_debug_type($value),
            ),
        );
    }

    public static function negativeValue(string $path, int $value): self
    {
        return new self(\sprintf('Usage limit configuration "%s" cannot be negative (given: %d).', $path, $value));
    }
}
