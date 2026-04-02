<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Stream;

use App\Domain\Collect\Stream\CollectDataPushStreamInterface;
use App\Domain\Collect\Stream\Exception\CollectDataStreamException;
use App\Domain\Collect\Stream\WebSocketStreamStatistics;

class NullPushStreamServer implements CollectDataPushStreamInterface
{
    private bool $listening = false;
    private int $listenCallCount = 0;
    private int $stopCallCount = 0;
    private ?\Exception $exceptionToThrow = null;
    private bool $shouldReturnFalse = false;
    private WebSocketStreamStatistics $statistics;

    public function __construct()
    {
        $this->statistics = new WebSocketStreamStatistics();
    }

    public function listen(): bool
    {
        ++$this->listenCallCount;

        if (null !== $this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }

        if ($this->shouldReturnFalse) {
            $this->listening = false;

            return false;
        }

        $this->listening = true;

        return true;
    }

    public function stop(): void
    {
        ++$this->stopCallCount;
        $this->listening = false;
    }

    public function isListening(): bool
    {
        return $this->listening;
    }

    public function getStatistics(): WebSocketStreamStatistics
    {
        return $this->statistics;
    }

    public function throwExceptionOnListen(string $message): void
    {
        $this->exceptionToThrow = new CollectDataStreamException($message);
    }

    public function throwGenericExceptionOnListen(string $message): void
    {
        $this->exceptionToThrow = new \RuntimeException($message);
    }

    public function setReturnFalse(): void
    {
        $this->shouldReturnFalse = true;
    }

    public function getListenCallCount(): int
    {
        return $this->listenCallCount;
    }

    public function getStopCallCount(): int
    {
        return $this->stopCallCount;
    }

    public function reset(): void
    {
        $this->listening = false;
        $this->listenCallCount = 0;
        $this->stopCallCount = 0;
        $this->exceptionToThrow = null;
        $this->shouldReturnFalse = false;
        $this->statistics->reset();
    }
}
