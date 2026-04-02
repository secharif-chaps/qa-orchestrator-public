<?php

declare(strict_types=1);

namespace App\Domain\Collect\Stream;

interface CollectDataPushStreamInterface
{
    public function listen(): bool;

    public function stop(): void;

    public function isListening(): bool;

    public function getStatistics(): WebSocketStreamStatistics;
}
