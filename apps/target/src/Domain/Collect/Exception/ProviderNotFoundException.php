<?php

declare(strict_types=1);

namespace App\Domain\Collect\Exception;

use App\Domain\Shared\NotFoundException;

class ProviderNotFoundException extends NotFoundException
{
    public static function withName(string $providerName): self
    {
        return new self(\sprintf('Collect provider "%s" not found', $providerName));
    }
}
