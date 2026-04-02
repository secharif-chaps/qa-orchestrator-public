<?php

declare(strict_types=1);

namespace App\Domain\Chat;

use App\Domain\Shared\DomainException;

class MessageCannotBeRetriedException extends DomainException
{
    public function __construct(string $messageId, MessageStatus $currentStatus)
    {
        parent::__construct(\sprintf(
            'Message "%s" cannot be retried because it is not in error status. Current status: %s',
            $messageId,
            $currentStatus->value,
        ));
    }
}
