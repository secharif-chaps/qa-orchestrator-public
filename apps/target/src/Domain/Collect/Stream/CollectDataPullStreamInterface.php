<?php

declare(strict_types=1);

namespace App\Domain\Collect\Stream;

interface CollectDataPullStreamInterface
{
    public function connect(string $collectTaskId, string $providerTaskId): bool;

    public function isConnected(): bool;

    public function disconnect(): void;
}
