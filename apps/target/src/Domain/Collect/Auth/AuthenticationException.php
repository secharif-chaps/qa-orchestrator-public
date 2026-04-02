<?php

declare(strict_types=1);

namespace App\Domain\Collect\Auth;

use App\Domain\Shared\DomainException;

class AuthenticationException extends DomainException
{
    public static function failedToAuthenticate(string $provider, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('Failed to authenticate with provider "%s"', $provider), 0, $previous);
    }

    public static function failedToRefreshToken(string $provider, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('Failed to refresh token for provider "%s"', $provider), 0, $previous);
    }

    public static function invalidCredentials(string $provider): self
    {
        return new self(\sprintf('Invalid credentials for provider "%s"', $provider));
    }

    public static function providerUnavailable(string $provider, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('Provider "%s" is temporarily unavailable', $provider), 0, $previous);
    }

    public static function tokenExpired(string $provider): self
    {
        return new self(\sprintf('Token expired for provider "%s"', $provider));
    }
}
