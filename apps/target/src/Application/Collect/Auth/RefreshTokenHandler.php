<?php

declare(strict_types=1);

namespace App\Application\Collect\Auth;

use App\Domain\Collect\Auth\AccessToken;
use App\Domain\Collect\Auth\AuthenticationException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\ItemInterface;

#[AsMessageHandler]
readonly class RefreshTokenHandler
{
    use AuthenticationHandlerTrait;

    public function __invoke(RefreshTokenAction $action): AccessToken
    {
        $cacheKey = $this->getCacheKey($action->provider);

        try {
            $this->logger->info('Refreshing token for provider', [
                'provider' => $action->provider,
            ]);

            // Remove existing cached token to force refresh
            $this->cache->delete($cacheKey);

            // Get refreshed token and cache it
            $encryptedToken = $this->cache->get($cacheKey, function (ItemInterface $item) use ($action) {
                $token = $this->authClient->refreshIfNeeded();
                $encryptedToken = $this->encryptToken($token);
                $cacheExpiration = $this->getCacheExpiration($token->expiresAt);
                $item->expiresAfter($cacheExpiration);

                $this->logger->info('Successfully refreshed token', [
                    'provider' => $action->provider,
                    'expires_at' => $token->expiresAt,
                    'cache_ttl' => $cacheExpiration,
                ]);

                return $encryptedToken;
            });

            return $this->decryptToken($encryptedToken);
        } catch (\Throwable $e) {
            $this->logger->error('Token refresh failed', [
                'provider' => $action->provider,
                'error' => $e->getMessage(),
            ]);

            // Remove invalid token from cache
            $this->cache->delete($cacheKey);

            throw AuthenticationException::failedToRefreshToken($action->provider, $e);
        }
    }
}
