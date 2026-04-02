<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus\Stream\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class FakeServerRequest implements ServerRequestInterface
{
    /** @var array<string, array<string>> */
    private array $headers = [];

    /**
     * @param array<string, string> $headers
     */
    public function __construct(array $headers = [])
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = [$value];
        }
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    public function getHeaderLine(string $name): string
    {
        if (!$this->hasHeader($name)) {
            return '';
        }

        return implode(', ', $this->headers[$name]);
    }

    public function getHeader(string $name): array
    {
        return $this->headers[$name] ?? [];
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function withHeader(string $name, $value): static
    {
        $new = clone $this;
        $new->headers[$name] = \is_array($value) ? $value : [$value];

        return $new;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $new = clone $this;
        if (!isset($new->headers[$name])) {
            $new->headers[$name] = [];
        }

        if (\is_array($value)) {
            $new->headers[$name] = array_merge($new->headers[$name], $value);
        } else {
            $new->headers[$name][] = $value;
        }

        return $new;
    }

    public function withoutHeader(string $name): static
    {
        $new = clone $this;
        unset($new->headers[$name]);

        return $new;
    }

    // Minimal implementations for other required methods
    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    public function withProtocolVersion(string $version): static
    {
        return $this;
    }

    public function getBody(): StreamInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function withBody(StreamInterface $body): static
    {
        return $this;
    }

    public function getRequestTarget(): string
    {
        return '/';
    }

    public function withRequestTarget(string $requestTarget): static
    {
        return $this;
    }

    public function getMethod(): string
    {
        return 'GET';
    }

    public function withMethod(string $method): static
    {
        return $this;
    }

    public function getUri(): UriInterface
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getServerParams(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCookieParams(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $cookies
     */
    public function withCookieParams(array $cookies): static
    {
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $query
     */
    public function withQueryParams(array $query): static
    {
        return $this;
    }

    /**
     * @return array<int, mixed>
     */
    public function getUploadedFiles(): array
    {
        return [];
    }

    /**
     * @param array<int, mixed> $uploadedFiles
     */
    public function withUploadedFiles(array $uploadedFiles): static
    {
        return $this;
    }

    public function getParsedBody(): null
    {
        return null;
    }

    /**
     * @param array<string, mixed>|object|null $data
     */
    public function withParsedBody($data): static
    {
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return [];
    }

    public function getAttribute(string $name, $default = null)
    {
        return $default;
    }

    public function withAttribute(string $name, $value): static
    {
        return $this;
    }

    public function withoutAttribute(string $name): static
    {
        return $this;
    }
}
