<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Bakus\Auth;

use App\Domain\Collect\Auth\AccessToken;
use App\Domain\Collect\Auth\AuthenticationClientInterface;
use App\Domain\Collect\Auth\AuthenticationCredentials;
use App\Domain\Collect\Auth\AuthenticationException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class BakusAuthenticationClient implements AuthenticationClientInterface
{
    private const string PROVIDER_NAME = 'bakus';
    private const int MAX_RETRIES = 3;
    private const array RETRY_DELAY_MS = [1000, 2000, 4000]; // Exponential backoff
    private ?AccessToken $currentToken = null;

    public function __construct(
        private readonly AuthenticationCredentials $credentials,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly int $maxRetries = self::MAX_RETRIES,
        private readonly int $retryDelay = 1000,
    ) {
    }

    public function authenticate(): AccessToken
    {
        $this->logger->info('Starting authentication with Bakus provider');

        try {
            $response = $this->makeAuthRequest($this->credentials->getAuthPayload());
            $data = $response->toArray();

            $this->currentToken = AccessToken::create($data);

            $this->logger->info('Successfully authenticated with Bakus', [
                'expires_at' => $this->currentToken->expiresAt,
                'has_refresh_token' => null !== $this->currentToken->refreshToken,
            ]);

            return $this->currentToken;
        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Invalid response format from Bakus provider', [
                'error' => $e->getMessage(),
            ]);
            throw AuthenticationException::failedToAuthenticate(self::PROVIDER_NAME, $e);
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during Bakus authentication', [
                'error' => $e->getMessage(),
                'error_class' => $e::class,
            ]);
            throw AuthenticationException::failedToAuthenticate(self::PROVIDER_NAME, $e);
        }
    }

    public function refreshIfNeeded(): AccessToken
    {
        if (null === $this->currentToken) {
            $this->logger->info('No current token, performing fresh authentication');

            return $this->authenticate();
        }

        if (!$this->currentToken->isExpiringSoon()) {
            $this->logger->debug('Current token is still valid', [
                'expires_at' => $this->currentToken->expiresAt,
                'time_remaining' => $this->currentToken->expiresAt - time(),
            ]);

            return $this->currentToken;
        }

        if (!$this->currentToken->hasValidRefreshToken()) {
            $this->logger->info('No valid refresh token available, performing fresh authentication');

            return $this->authenticate();
        }

        $this->logger->info('Refreshing token for Bakus provider');

        try {
            if (null === $this->currentToken->refreshToken) {
                throw new \RuntimeException('Refresh token is null when it should be valid');
            }
            $payload = $this->credentials->getRefreshPayload($this->currentToken->refreshToken);
            $response = $this->makeAuthRequest($payload);
            $data = $response->toArray();

            $this->currentToken = AccessToken::create($data);

            $this->logger->info('Successfully refreshed Bakus token', [
                'expires_at' => $this->currentToken->expiresAt,
            ]);

            return $this->currentToken;
        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Invalid response format during token refresh', [
                'error' => $e->getMessage(),
            ]);

            // Reset current token on refresh failure
            $this->currentToken = null;
            throw AuthenticationException::failedToRefreshToken(self::PROVIDER_NAME, $e);
        } catch (AuthenticationException $e) {
            // Reset current token on refresh failure
            $this->currentToken = null;
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Bakus token refresh failed', [
                'error' => $e->getMessage(),
                'error_class' => $e::class,
            ]);

            // Reset current token on refresh failure
            $this->currentToken = null;

            throw AuthenticationException::failedToRefreshToken(self::PROVIDER_NAME, $e);
        }
    }

    public function getCurrentToken(): ?AccessToken
    {
        return $this->currentToken;
    }

    public function isAuthenticated(): bool
    {
        if (null === $this->currentToken) {
            return false;
        }

        if ($this->currentToken->isExpired()) {
            $this->logger->debug('Current token has expired');

            return false;
        }

        return true;
    }

    /**
     * @param array<string, string> $payload
     */
    private function makeAuthRequest(array $payload): ResponseInterface
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                $this->logger->debug('Making auth request', [
                    'attempt' => $attempt + 1,
                    'max_retries' => $this->maxRetries,
                    'url' => $this->credentials->authUrl,
                ]);

                $response = $this->httpClient->request('POST', $this->credentials->authUrl, [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'Accept' => 'application/json',
                        'Authorization' => $this->credentials->getBasicAuthorizationHeader(),
                    ],
                    'body' => $payload,
                    'timeout' => 30,
                ]);

                // Trigger HTTP exception by accessing content if status >= 400
                $response->getContent();

                return $response;
            } catch (HttpExceptionInterface $e) {
                $lastException = $e;
                $statusCode = $e->getResponse()
                    ->getStatusCode();

                if (401 === $statusCode || 403 === $statusCode) {
                    // Don't retry authentication failures
                    throw AuthenticationException::invalidCredentials(self::PROVIDER_NAME);
                }

                if ($statusCode < 500) {
                    // Don't retry client errors (except 401/403 handled above)
                    throw $e;
                }

                // Retry server errors with backoff
                $this->logger->warning('Bakus server error, retrying', [
                    'attempt' => $attempt + 1,
                    'status_code' => $statusCode,
                    'error' => $e->getMessage(),
                ]);
            } catch (TransportExceptionInterface $e) {
                $lastException = $e;
                $this->logger->warning('Bakus transport error, retrying', [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);
            }

            ++$attempt;

            if ($attempt < $this->maxRetries && $this->retryDelay > 0) {
                $delay = self::RETRY_DELAY_MS[$attempt - 1];
                usleep($delay * $this->retryDelay); // Convert to microseconds
            }
        }

        throw AuthenticationException::providerUnavailable(self::PROVIDER_NAME, $lastException);
    }
}
