<?php

declare(strict_types=1);

namespace App\Domain\Collect\Exception;

class InactiveWatchFileException extends CollectException
{
    public static function forWatchFileId(string $watchFileId): self
    {
        return new self(\sprintf('Cannot create collect task for inactive watch file with ID "%s"', $watchFileId));
    }
}
