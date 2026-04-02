<?php

declare(strict_types=1);

namespace App\Domain\Collect\Auth;

interface AuthenticationClientInterface
{
    /**
     * Authenticates with the provider and returns an access token.
     */
    public function authenticate(): AccessToken;

    /**
     * Refreshes the token if needed.
     */
    public function refreshIfNeeded(): AccessToken;

    /**
     * Returns the current access token or null if not authenticated.
     */
    public function getCurrentToken(): ?AccessToken;

    /**
     * Checks if the client is authenticated with a valid token.
     */
    public function isAuthenticated(): bool;
}
