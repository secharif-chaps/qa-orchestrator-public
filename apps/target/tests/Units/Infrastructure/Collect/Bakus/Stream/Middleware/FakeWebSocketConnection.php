<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus\Stream\Middleware;

use Psr\Http\Message\ResponseInterface;
use WebSocket\Connection;
use WebSocket\Message\Close;

class FakeWebSocketConnection extends Connection
{
    /** @var array<string, mixed> */
    private array $metadata = [];
    private bool $closed = false;
    private ?int $closeCode = null;
    private ?string $closeReason = null;
    private ?ResponseInterface $handshakeResponse = null;

    public function __construct()
    {
        // Don't call parent constructor as it requires a real socket
    }

    public function __destruct()
    {
        // Override parent destructor to avoid accessing uninitialized properties
    }

    public function setMeta(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getMeta(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }

    public function close(int $status = 1000, string $message = 'ttfn'): Close
    {
        $this->closed = true;
        $this->closeCode = $status;
        $this->closeReason = $message;

        // Return a fake Close message
        return new Close($status, $message);
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function getCloseCode(): ?int
    {
        return $this->closeCode;
    }

    public function getCloseReason(): ?string
    {
        return $this->closeReason;
    }

    public function reset(): void
    {
        $this->metadata = [];
        $this->closed = false;
        $this->closeCode = null;
        $this->closeReason = null;
        $this->handshakeResponse = null;
    }

    public function setHandshakeResponse(ResponseInterface $response): self
    {
        $this->handshakeResponse = $response;

        return $this;
    }

    public function getHandshakeResponse(): ?ResponseInterface
    {
        return $this->handshakeResponse;
    }

    public function isRejected(): bool
    {
        return null !== $this->handshakeResponse && $this->handshakeResponse->getStatusCode() >= 400;
    }
}
