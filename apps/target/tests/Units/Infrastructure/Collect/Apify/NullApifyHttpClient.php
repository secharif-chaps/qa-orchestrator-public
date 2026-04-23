<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify;

use App\Domain\Collect\ApifyClientInterface;

class NullApifyHttpClient implements ApifyClientInterface
{
    /** @var array<string, mixed|\Closure> */
    private array $responses = [];
    private ?\Throwable $throwOnRequest = null;

    /**
     * @param mixed|\Closure(string, string, array<string, mixed>): mixed $response
     */
    public function addResponse(string $pathSubstring, mixed $response): void
    {
        $this->responses[$pathSubstring] = $response;
        // Sort by path length descending so more specific paths match first
        uksort($this->responses, static fn (string $a, string $b) => \strlen($b) - \strlen($a));
    }

    public function throwOnRequest(\Throwable $exception): void
    {
        $this->throwOnRequest = $exception;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>|string|null
     */
    public function request(string $method, string $path, array $options = [], bool $toArray = true): mixed
    {
        if (null !== $this->throwOnRequest) {
            throw $this->throwOnRequest;
        }

        foreach ($this->responses as $pathSubstring => $response) {
            if (str_contains($path, $pathSubstring)) {
                if ($response instanceof \Closure) {
                    /** @var array<mixed>|string|null $result */
                    $result = $response($method, $path, $options);

                    return $result;
                }

                /** @var array<mixed>|string|null $response */
                return $response;
            }
        }

        throw new \RuntimeException(\sprintf('No response configured for path: %s', $path));
    }
}
