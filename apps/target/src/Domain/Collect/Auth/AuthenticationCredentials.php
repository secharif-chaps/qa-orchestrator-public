<?php

declare(strict_types=1);

namespace App\Domain\Collect\Auth;

readonly class AuthenticationCredentials
{
    public function __construct(
        #[\SensitiveParameter]
        public string $clientId,
        #[\SensitiveParameter]
        public string $clientSecret,
        #[\SensitiveParameter]
        public string $username,
        #[\SensitiveParameter]
        public string $password,
        public string $authUrl,
        public string $grantType = 'password',
    ) {
        if (empty($this->clientId)) {
            throw new \InvalidArgumentException('Client ID cannot be empty');
        }
        if (empty($this->authUrl)) {
            throw new \InvalidArgumentException('Auth URL cannot be empty');
        }
    }

    public function getBasicAuthorizationHeader(): string
    {
        return 'Basic ' . base64_encode(\sprintf('%s:%s', $this->clientId, $this->clientSecret));
    }

    /**
     * @return array{
     *     username: string,
     *     password: string,
     *     grant_type: string,
     * }
     */
    public function getAuthPayload(): array
    {
        return [
            'grant_type' => $this->grantType,
            'username' => $this->username,
            'password' => $this->password,
        ];
    }

    /**
     * @return array{
     *     grant_type: string,
     *     refresh_token: string,
     * }
     */
    public function getRefreshPayload(string $refreshToken): array
    {
        return [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];
    }
}
