<?php

declare(strict_types=1);

namespace App\Domain\WatchFile\Exception;

use App\Domain\Shared\NotFoundException;

class WatchFileActorNotFoundException extends NotFoundException
{
    public function __construct(
        string $message = 'WatchFile Actor link not found',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
