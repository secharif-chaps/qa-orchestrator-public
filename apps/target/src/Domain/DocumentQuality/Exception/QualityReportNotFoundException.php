<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality\Exception;

use App\Domain\Shared\NotFoundException;

class QualityReportNotFoundException extends NotFoundException
{
    public function __construct(
        string $message = 'QualityReport not found',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
