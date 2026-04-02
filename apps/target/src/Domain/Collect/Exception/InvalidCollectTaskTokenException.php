<?php

declare(strict_types=1);

namespace App\Domain\Collect\Exception;

class InvalidCollectTaskTokenException extends CollectException
{
    public static function fromDecodingError(\Throwable $previous): self
    {
        return new self(\sprintf('Invalid collect task token: "%s"', $previous->getMessage()), previous: $previous);
    }
}
