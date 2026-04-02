<?php

namespace App\Infrastructure\Collect\Bakus\Stream;

trait WebSocketUtil
{
    public function getCloseCodeMeaning(?int $code): string
    {
        return match ($code) {
            1000 => 'Normal Closure',
            1001 => 'Going Away',
            1002 => 'Protocol Error',
            1003 => 'Unsupported Data',
            1005 => 'No Status Received',
            1006 => 'Abnormal Closure',
            1007 => 'Invalid frame payload data',
            1008 => 'Policy Violation',
            1009 => 'Message Too Big',
            1010 => 'Mandatory Extension',
            1011 => 'Internal Server Error',
            1015 => 'TLS handshake failure',
            default => $code ? "Unknown code ($code)" : 'Unknown',
        };
    }
}
