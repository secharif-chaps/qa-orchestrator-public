<?php

declare(strict_types=1);

namespace App\Domain\Document\Exception;

use App\Domain\Shared\DomainException;

class UpdateDocumentSummaryException extends DomainException
{
    public function __construct(
        string $message = 'Failed to update document summary',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
