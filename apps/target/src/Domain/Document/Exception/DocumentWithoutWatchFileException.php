<?php

declare(strict_types=1);

namespace App\Domain\Document\Exception;

use App\Domain\Shared\DomainException;

class DocumentWithoutWatchFileException extends DomainException
{
    public function __construct(
        string $message = 'Document has no associated watch file',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function forDocumentId(string $documentId): self
    {
        return new self(\sprintf('Document with ID "%s" has no associated watch file', $documentId));
    }
}
