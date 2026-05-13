<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality\Exception;

class AdblockSourceUnavailableException extends \RuntimeException
{
    public function __construct(string $sourceName, ?\Throwable $previous = null)
    {
        parent::__construct(
            \sprintf('Adblock source "%s" is unavailable', $sourceName),
            previous: $previous,
        );
    }
}
