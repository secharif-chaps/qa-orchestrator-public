<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Stream;

use WebSocket\Connection;
use WebSocket\Exception\ExceptionInterface;
use WebSocket\Message\Close;
use WebSocket\Message\Text;
use WebSocket\Server;

class NullWebSocketServer extends Server
{
    private bool $shouldThrowOnStart = false;
    private ?\Exception $exceptionToThrow = null;
    private bool $listening = false;
    private int $startCallCount = 0;
    private int $stopCallCount = 0;
    private int $disconnectCallCount = 0;

    /** @var \Closure(Server, Connection, Text): void|null */
    private ?\Closure $onTextCallback = null;

    /** @var \Closure(Server, Connection, Close): void|null */
    private ?\Closure $onCloseCallback = null;

    /** @var \Closure(Server, ?Connection, ExceptionInterface): void|null */
    private ?\Closure $onErrorCallback = null;

    public function __construct()
    {
        // Don't call parent constructor as it requires real server setup
    }

    public function throwExceptionOnStart(\Exception $exception): void
    {
        $this->shouldThrowOnStart = true;
        $this->exceptionToThrow = $exception;
    }

    public function start(int|float|null $timeout = null): void
    {
        ++$this->startCallCount;
        $this->listening = true;

        if ($this->shouldThrowOnStart && null !== $this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }
    }

    public function stop(): void
    {
        ++$this->stopCallCount;
        $this->listening = false;
    }

    public function disconnect(): void
    {
        ++$this->disconnectCallCount;
    }

    public function onText(\Closure $callback): Server
    {
        $this->onTextCallback = $callback;

        return $this;
    }

    public function onClose(\Closure $callback): Server
    {
        $this->onCloseCallback = $callback;

        return $this;
    }

    public function onError(\Closure $callback): Server
    {
        $this->onErrorCallback = $callback;

        return $this;
    }

    /**
     * Trigger the onText callback manually for testing.
     */
    public function triggerOnText(Connection $connection, Text $message): void
    {
        if (null !== $this->onTextCallback) {
            ($this->onTextCallback)($this, $connection, $message);
        }
    }

    /**
     * Trigger the onClose callback manually for testing.
     */
    public function triggerOnClose(Connection $connection, Close $message): void
    {
        if (null !== $this->onCloseCallback) {
            ($this->onCloseCallback)($this, $connection, $message);
        }
    }

    /**
     * Trigger the onError callback manually for testing.
     */
    public function triggerOnError(?Connection $connection, ExceptionInterface $exception): void
    {
        if (null !== $this->onErrorCallback) {
            ($this->onErrorCallback)($this, $connection, $exception);
        }
    }

    public function isListening(): bool
    {
        return $this->listening;
    }

    public function getStartCallCount(): int
    {
        return $this->startCallCount;
    }

    public function getStopCallCount(): int
    {
        return $this->stopCallCount;
    }

    public function getDisconnectCallCount(): int
    {
        return $this->disconnectCallCount;
    }

    public function reset(): void
    {
        $this->shouldThrowOnStart = false;
        $this->exceptionToThrow = null;
        $this->listening = false;
        $this->startCallCount = 0;
        $this->stopCallCount = 0;
        $this->disconnectCallCount = 0;
        $this->onTextCallback = null;
        $this->onCloseCallback = null;
        $this->onErrorCallback = null;
    }
}
