<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Stream;

use App\Domain\Collect\Stream\CollectDataPullStreamInterface;
use App\Domain\Collect\Stream\Exception\CollectDataStreamException;

class NullWebSocketConnection implements CollectDataPullStreamInterface
{
    private bool $isConnected = false;
    private bool $shouldThrowException = false;
    private ?string $exceptionMessage = null;
    private bool $shouldThrowGenericException = false;
    private ?string $genericExceptionMessage = null;

    /**
     * @var list<array{collectTaskId: string, queryId: string}>
     */
    private array $connectionCalls = [];
    private int $disconnectCallCount = 0;

    public function connect(string $collectTaskId, string $providerTaskId): bool
    {
        $this->connectionCalls[] = [
            'collectTaskId' => $collectTaskId,
            'queryId' => $providerTaskId,
        ];

        if ($this->shouldThrowException) {
            throw new CollectDataStreamException($this->exceptionMessage ?? 'Connection failed');
        }

        if ($this->shouldThrowGenericException) {
            throw new \RuntimeException($this->genericExceptionMessage ?? 'Generic error');
        }

        $this->isConnected = true;

        return true;
    }

    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    public function disconnect(): void
    {
        ++$this->disconnectCallCount;
        $this->isConnected = false;
    }

    public function throwExceptionOnConnect(string $message = 'Connection failed'): void
    {
        $this->shouldThrowException = true;
        $this->exceptionMessage = $message;
    }

    public function throwGenericExceptionOnConnect(string $message = 'Generic error'): void
    {
        $this->shouldThrowGenericException = true;
        $this->genericExceptionMessage = $message;
    }

    /**
     * @return list<array{collectTaskId: string, queryId: string}>
     */
    public function getConnectionCalls(): array
    {
        return $this->connectionCalls;
    }

    public function getDisconnectCallCount(): int
    {
        return $this->disconnectCallCount;
    }

    public function reset(): void
    {
        $this->isConnected = false;
        $this->shouldThrowException = false;
        $this->exceptionMessage = null;
        $this->shouldThrowGenericException = false;
        $this->genericExceptionMessage = null;
        $this->connectionCalls = [];
        $this->disconnectCallCount = 0;
    }
}
