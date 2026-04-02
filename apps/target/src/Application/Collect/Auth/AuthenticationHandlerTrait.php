<?php

declare(strict_types=1);

namespace App\Application\Collect\Auth;

use App\Domain\Collect\Auth\AccessToken;
use App\Domain\Collect\Auth\AuthenticationClientInterface;
use App\Domain\Encryption\EncryptorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;

trait AuthenticationHandlerTrait
{
    private const string CACHE_KEY_PREFIX = 'collect_auth_token_';
    private const int CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly AuthenticationClientInterface $authClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly EncryptorInterface $encryptor,
        private readonly SerializerInterface $serializer,
    ) {
    }

    private function getCacheKey(string $provider): string
    {
        return self::CACHE_KEY_PREFIX . $provider;
    }

    private function getCacheExpiration(int $expiresAt): int
    {
        $tokenTtl = $expiresAt - time() - 300; // Token TTL - buffer 5min

        return max(0, min(self::CACHE_TTL, $tokenTtl));
    }

    private function encryptToken(AccessToken $token): string
    {
        $serializedToken = $this->serializer->serialize($token, 'json');

        return $this->encryptor->encrypt($serializedToken);
    }

    private function decryptToken(string $encryptedToken): AccessToken
    {
        $serializedToken = $this->encryptor->decrypt($encryptedToken);

        return $this->serializer->deserialize($serializedToken, AccessToken::class, 'json');
    }
}
