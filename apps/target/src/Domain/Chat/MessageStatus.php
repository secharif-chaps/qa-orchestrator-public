<?php

declare(strict_types=1);

namespace App\Domain\Chat;

enum MessageStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Error = 'error';

    public function isError(): bool
    {
        return self::Error === $this;
    }
}
