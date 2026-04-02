<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Stream;

use App\Domain\Collect\Stream\CollectDataPullStreamInterface;

class NullPullStreamClient implements CollectDataPullStreamInterface
{
    private bool $connected = false;
    private bool $shouldFail = false;
    private int $connectCallCount = 0;
    private int $disconnectCallCount = 0;

    public function setFail(bool $fail): void
    {
        $this->shouldFail = $fail;
    }

    public function connect(string $collectTaskId, string $providerTaskId): bool
    {
        ++$this->connectCallCount;

        if ($this->shouldFail) {
            return false;
        }

        $this->connected = true;

        return true;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function disconnect(): void
    {
        ++$this->disconnectCallCount;
        $this->connected = false;
    }

    public function getConnectCallCount(): int
    {
        return $this->connectCallCount;
    }

    public function getDisconnectCallCount(): int
    {
        return $this->disconnectCallCount;
    }

    public function reset(): void
    {
        $this->connected = false;
        $this->shouldFail = false;
        $this->connectCallCount = 0;
        $this->disconnectCallCount = 0;
    }
}
