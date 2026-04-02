<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify\Client;

use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\Exception\CollectHttpException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApifyHttpClient
{
    private const int MAX_RETRY_ATTEMPTS = 3;
    private const int DEFAULT_RETRY_DELAY_MS = 1000;
    private const int MAX_RETRY_DELAY_MS = 30_000;

    public function __construct(
        #[Autowire(env: 'APIFY_API_URL')]
        private readonly string $apiUrl,
        #[\SensitiveParameter]
        #[Autowire(env: 'APIFY_API_TOKEN')]
        private readonly string $apiToken,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly float $timeout = 30.0,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>|string|null
     */
    public function request(string $method, string $path, array $options = [], bool $toArray = true): mixed
    {
        return $this->doRequest($method, $path, $options, $toArray, 0);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>|string|null
     */
    private function doRequest(string $method, string $path, array $options, bool $toArray, int $attempt): mixed
    {
        try {
            $response = $this->httpClient->request($method, $this->buildUrl($path), $this->mergeOptions($options));

            if (Response::HTTP_NO_CONTENT === $response->getStatusCode()) {
                return null;
            }

            if ($toArray) {
                return $response->toArray();
            }

            return $response->getContent();
        } catch (HttpExceptionInterface $exception) {
            $statusCode = $exception->getResponse()
->getStatusCode();

            if (Response::HTTP_TOO_MANY_REQUESTS === $statusCode && $attempt < self::MAX_RETRY_ATTEMPTS) {
                $retryAfter = $this->getRetryAfterDelay($exception);

                $this->logger->warning('Apify rate limit hit, retrying', [
                    'provider_name' => 'apify',
                    'attempt' => $attempt + 1,
                    'retry_after_ms' => $retryAfter,
                    'path' => $path,
                ]);

                usleep($retryAfter * 1000);

                return $this->doRequest($method, $path, $options, $toArray, $attempt + 1);
            }

            throw new CollectHttpException($exception->getResponse(), \sprintf(
                'Apify request failed %s %s - %s',
                $method,
                $path,
                $exception->getMessage()
            ), $statusCode, $exception, );
        } catch (TransportExceptionInterface $exception) {
            throw new CollectException(\sprintf(
                'Apify transport error %s %s',
                $method,
                $path
            ), $exception->getCode(), $exception, );
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function mergeOptions(array $options): array
    {
        return array_merge_recursive([
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => \sprintf('Bearer %s', $this->apiToken),
            ],
            'timeout' => $this->timeout,
        ], $options);
    }

    private function buildUrl(string $path): string
    {
        if (str_starts_with($path, '/')) {
            $path = substr($path, 1);
        }

        return \sprintf('%s/%s', rtrim($this->apiUrl, '/'), $path);
    }

    private function getRetryAfterDelay(HttpExceptionInterface $exception): int
    {
        $headers = $exception->getResponse()
->getHeaders(false);
        $retryAfter = $headers['retry-after'][0] ?? null;

        if (null !== $retryAfter && is_numeric($retryAfter)) {
            return min((int) $retryAfter * 1000, self::MAX_RETRY_DELAY_MS);
        }

        return self::DEFAULT_RETRY_DELAY_MS;
    }
}
