<?php

declare(strict_types=1);

namespace App\Domain\Document\Exception;

use App\Domain\Shared\DomainException;

class DocumentSaveFailedException extends DomainException
{
    public function __construct(
        string $message = 'Failed to save document',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
