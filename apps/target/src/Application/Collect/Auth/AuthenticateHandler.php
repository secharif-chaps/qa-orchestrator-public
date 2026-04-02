<?php

declare(strict_types=1);

namespace App\Application\Collect\Auth;

use App\Domain\Collect\Auth\AccessToken;
use App\Domain\Collect\Auth\AuthenticationException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\ItemInterface;

#[AsMessageHandler]
readonly class AuthenticateHandler
{
    use AuthenticationHandlerTrait;

    public function __invoke(AuthenticateAction $action): AccessToken
    {
        $cacheKey = $this->getCacheKey($action->provider);

        try {
            if ($action->forceRefresh) {
                $this->cache->delete($cacheKey);
                $this->logger->info('Forcing authentication refresh', [
                    'provider' => $action->provider,
                ]);
            }

            $encryptedToken = $this->cache->get($cacheKey, function (ItemInterface $item) use ($action) {
                $this->logger->info('Authenticating with provider', [
                    'provider' => $action->provider,
                ]);

                $token = $this->authClient->authenticate();
                $encryptedToken = $this->encryptToken($token);
                $cacheExpiration = $this->getCacheExpiration($token->expiresAt);
                $item->expiresAfter($cacheExpiration);

                $this->logger->info('Successfully authenticated', [
                    'provider' => $action->provider,
                    'expires_at' => $token->expiresAt,
                    'cache_ttl' => $cacheExpiration,
                ]);

                return $encryptedToken;
            });

            return $this->decryptToken($encryptedToken);
        } catch (\Throwable $e) {
            $this->logger->error('Authentication failed', [
                'provider' => $action->provider,
                'error' => $e->getMessage(),
            ]);

            throw AuthenticationException::failedToAuthenticate($action->provider, $e);
        }
    }
}
