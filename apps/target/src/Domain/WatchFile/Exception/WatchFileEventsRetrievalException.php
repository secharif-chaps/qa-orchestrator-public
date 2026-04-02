<?php

declare(strict_types=1);

namespace App\Domain\WatchFile\Exception;

use App\Domain\Shared\DomainException;

class WatchFileEventsRetrievalException extends DomainException
{
    public static function fromOpenSearchError(string $watchFileId, \Throwable $previous): self
    {
        return new self(
            \sprintf('Failed to retrieve events for WatchFile %s from the search index', $watchFileId),
            previous: $previous
        );
    }
}
