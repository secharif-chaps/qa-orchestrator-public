<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify\Auth;

use App\Domain\Collect\Auth\AccessToken;
use App\Domain\Collect\Auth\AuthenticationClientInterface;
use App\Domain\Collect\Auth\AuthenticationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Apify uses a static Bearer API token (no OAuth2).
 * Authentication is verified by calling GET /v2/users/me.
 * Token refresh methods are no-op since the token never expires.
 */
class ApifyAuthenticationClient implements AuthenticationClientInterface
{
    private const string PROVIDER_NAME = 'apify';
    private const int STATIC_TOKEN_TTL = 365 * 24 * 3600; // 1 year (token never expires)
    private ?AccessToken $currentToken = null;

    public function __construct(
        #[Autowire(env: 'APIFY_API_URL')]
        private readonly string $apiUrl,
        #[\SensitiveParameter]
        #[Autowire(env: 'APIFY_API_TOKEN')]
        private readonly string $apiToken,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function authenticate(): AccessToken
    {
        $this->logger->info('Verifying Apify API token', [
            'provider_name' => self::PROVIDER_NAME,
        ]);

        try {
            $response = $this->httpClient->request('GET', \sprintf('%s/users/me', rtrim($this->apiUrl, '/')), [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => \sprintf('Bearer %s', $this->apiToken),
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();
            $username = $data['data']['username'] ?? 'unknown';

            $this->currentToken = new AccessToken(
                token: $this->apiToken,
                expiresAt: time() + self::STATIC_TOKEN_TTL,
            );

            $this->logger->info('Apify token verified successfully', [
                'provider_name' => self::PROVIDER_NAME,
                'username' => $username,
            ]);

            return $this->currentToken;
        } catch (HttpExceptionInterface $exception) {
            $statusCode = $exception->getResponse()
->getStatusCode();

            if (401 === $statusCode || 403 === $statusCode) {
                $this->logger->error('Invalid Apify API token', [
                    'provider_name' => self::PROVIDER_NAME,
                    'status_code' => $statusCode,
                ]);
                throw AuthenticationException::invalidCredentials(self::PROVIDER_NAME);
            }

            $this->logger->error('Apify authentication failed', [
                'provider_name' => self::PROVIDER_NAME,
                'status_code' => $statusCode,
                'error' => $exception->getMessage(),
            ]);
            throw AuthenticationException::failedToAuthenticate(self::PROVIDER_NAME, $exception);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Apify provider unreachable', [
                'provider_name' => self::PROVIDER_NAME,
                'error' => $exception->getMessage(),
            ]);
            throw AuthenticationException::providerUnavailable(self::PROVIDER_NAME, $exception);
        }
    }

    /**
     * No-op for Apify: the token is static and never needs refreshing.
     * Returns the current token or authenticates if not yet verified.
     */
    public function refreshIfNeeded(): AccessToken
    {
        if (null === $this->currentToken) {
            return $this->authenticate();
        }

        return $this->currentToken;
    }

    public function getCurrentToken(): ?AccessToken
    {
        return $this->currentToken;
    }

    public function isAuthenticated(): bool
    {
        return null !== $this->currentToken;
    }
}
