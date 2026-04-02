<?php

declare(strict_types=1);

namespace App\Domain\Collect\Exception;

class InactiveSourceException extends CollectException
{
    public static function forSourceId(string $sourceId): self
    {
        return new self(\sprintf('Cannot create collect task for inactive source with ID "%s"', $sourceId));
    }
}
