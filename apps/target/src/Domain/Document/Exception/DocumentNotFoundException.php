<?php

declare(strict_types=1);

namespace App\Domain\Document\Exception;

use App\Domain\Shared\NotFoundException;

/**
 * Exception thrown when a Document cannot be found.
 */
class DocumentNotFoundException extends NotFoundException
{
    public function __construct(string $message = 'Document not found', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function withId(string $id): self
    {
        return new self(\sprintf('Document with ID "%s" not found', $id));
    }
}
