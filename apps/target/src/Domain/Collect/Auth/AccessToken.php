<?php

declare(strict_types=1);

namespace App\Domain\Collect\Auth;

readonly class AccessToken
{
    public function __construct(
        public string $token,
        public int $expiresAt,
        public ?string $refreshToken = null,
        public ?int $refreshExpiresAt = null,
        public string $tokenType = 'Bearer',
        public string $scope = '',
    ) {
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < time();
    }

    public function isExpiringSoon(int $bufferSeconds = 300): bool
    {
        return $this->expiresAt < (time() + $bufferSeconds);
    }

    public function hasValidRefreshToken(): bool
    {
        return null !== $this->refreshToken
               && null !== $this->refreshExpiresAt
               && $this->refreshExpiresAt > time();
    }

    public function toAuthenticationHeader(): string
    {
        return "{$this->tokenType} {$this->token}";
    }

    /**
     * @param array{
     *     access_token?: mixed,
     *     expires_in?: mixed,
     *     refresh_token?: string,
     *     refresh_expires_in?: int,
     *     token_type?: string,
     *     scope?: string,
     * } $data
     */
    public static function create(array $data): self
    {
        if (empty($data['access_token']) || !\is_string($data['access_token'])) {
            throw new \InvalidArgumentException('access_token is required and must be a non-empty string');
        }

        if (empty($data['expires_in']) || !is_numeric($data['expires_in'])) {
            throw new \InvalidArgumentException('expires_in is required and must be a positive integer');
        }

        $expiresIn = \is_string($data['expires_in']) ? (int) $data['expires_in'] : $data['expires_in'];
        if (!\is_int($expiresIn) || $expiresIn <= 0) {
            throw new \InvalidArgumentException('expires_in is required and must be a positive integer');
        }

        return new self(
            token: $data['access_token'],
            expiresAt: time() + $expiresIn,
            refreshToken: $data['refresh_token'] ?? null,
            refreshExpiresAt: isset($data['refresh_expires_in']) ? time() + $data['refresh_expires_in'] : null,
            tokenType: $data['token_type'] ?? 'Bearer',
            scope: $data['scope'] ?? '',
        );
    }
}
