<?php

declare(strict_types=1);

namespace App\Domain\Collect\Exception;

use App\Domain\Shared\DomainException;

class UnknownProviderException extends DomainException
{
    public static function withName(string $providerName): self
    {
        return new self(\sprintf('Unknown collect provider "%s"', $providerName));
    }
}
