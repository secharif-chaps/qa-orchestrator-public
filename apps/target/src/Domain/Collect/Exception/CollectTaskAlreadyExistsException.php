<?php

declare(strict_types=1);

namespace App\Domain\Collect\Exception;

class CollectTaskAlreadyExistsException extends CollectException
{
    public static function forSourceId(string $sourceId): self
    {
        return new self(\sprintf('An active collect task already exists for source with ID "%s"', $sourceId));
    }
}
