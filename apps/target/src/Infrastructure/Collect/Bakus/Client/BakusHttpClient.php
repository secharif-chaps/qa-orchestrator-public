<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Bakus\Client;

use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\Exception\CollectHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BakusHttpClient extends BakusAbstractClient
{
    private const int MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly string $apiUrl,
        private readonly HttpClientInterface $httpClient,
        MessageBusInterface $messageBus,
    ) {
        parent::__construct($messageBus);
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
            $response = $this->httpClient
                ->request($method, $this->getApiUrl($path), $this->handleOptions($options));

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

            // retry if token expired
            if (
                Response::HTTP_UNAUTHORIZED === $statusCode
                && $attempt < self::MAX_ATTEMPTS
            ) {
                $this->refreshToken();

                return $this->doRequest($method, $path, $options, $toArray, ++$attempt);
            }

            throw new CollectHttpException($exception->getResponse(), \sprintf(
                'Failed to request %s => %s - %s',
                $method,
                $path,
                $exception->getMessage()
            ), $statusCode, $exception, );
        } catch (TransportExceptionInterface $exception) {
            throw new CollectException(\sprintf(
                'Failed to request %s => %s',
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
    private function handleOptions(array $options): array
    {
        return array_merge_recursive([
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $this
                    ->getToken()
                    ->toAuthenticationHeader(),
            ],
        ], $options);
    }

    /**
     * @return non-empty-string
     */
    private function getApiUrl(string $path): string
    {
        $url = $this->apiUrl;

        if (str_starts_with($path, '/')) {
            $path = substr($path, 1);
        }

        return \sprintf('%s/%s', $url, $path);
    }
}
