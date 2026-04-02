<?php

declare(strict_types=1);

namespace App\Domain\Chat;

use App\Domain\Shared\DomainException;

class MaxRetryAttemptsExceededException extends DomainException
{
    public function __construct(string $messageId, int $maxAttempts)
    {
        parent::__construct(\sprintf(
            'Message "%s" has reached the maximum retry limit of %d attempts.',
            $messageId,
            $maxAttempts,
        ));
    }
}
