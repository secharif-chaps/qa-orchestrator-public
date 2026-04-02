<?php

declare(strict_types=1);

namespace App\Domain\Collect\Auth;

class TokenExpiredException extends AuthenticationException
{
    public static function create(string $provider): self
    {
        return new self(\sprintf('Access token has expired for provider "%s"', $provider));
    }
}
