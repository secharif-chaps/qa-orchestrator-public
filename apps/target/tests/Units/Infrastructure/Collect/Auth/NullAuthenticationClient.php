<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Auth;

use App\Domain\Collect\Auth\AccessToken;
use App\Domain\Collect\Auth\AuthenticationClientInterface;
use App\Domain\Collect\Auth\AuthenticationException;

class NullAuthenticationClient implements AuthenticationClientInterface
{
    private ?AccessToken $token = null;
    private bool $shouldFailAuthentication = false;
    private bool $shouldFailRefresh = false;
    private int $tokenCounter = 0;
    private bool $forceNewToken = false;
    private ?int $fixedTimestamp = null;

    public function authenticate(): AccessToken
    {
        if ($this->shouldFailAuthentication) {
            throw AuthenticationException::failedToAuthenticate('null');
        }

        // Only increment counter if explicitly forced
        if ($this->forceNewToken) {
            ++$this->tokenCounter;
            $this->forceNewToken = false;
        }

        $baseTime = $this->fixedTimestamp ?? time();
        $this->token = new AccessToken(
            token: 'test-access-token',
            expiresAt: $baseTime + 3600 + $this->tokenCounter,
            refreshToken: 'test-refresh-token',
            refreshExpiresAt: $baseTime + 7200 + $this->tokenCounter,
            tokenType: 'Bearer',
            scope: 'read write'
        );

        return $this->token;
    }

    public function refreshIfNeeded(): AccessToken
    {
        if ($this->shouldFailRefresh) {
            throw AuthenticationException::failedToRefreshToken('null');
        }

        if (null === $this->token || $this->token->isExpiringSoon()) {
            return $this->authenticate();
        }

        return $this->token;
    }

    public function getCurrentToken(): ?AccessToken
    {
        return $this->token;
    }

    public function isAuthenticated(): bool
    {
        return null !== $this->token && !$this->token->isExpired();
    }

    public function setToken(?AccessToken $token): void
    {
        $this->token = $token;
    }

    public function setShouldFailAuthentication(bool $shouldFail): void
    {
        $this->shouldFailAuthentication = $shouldFail;
    }

    public function setShouldFailRefresh(bool $shouldFail): void
    {
        $this->shouldFailRefresh = $shouldFail;
    }

    public function forceNewToken(): void
    {
        $this->forceNewToken = true;
    }

    public function setFixedTimestamp(?int $timestamp): void
    {
        $this->fixedTimestamp = $timestamp;
    }
}
