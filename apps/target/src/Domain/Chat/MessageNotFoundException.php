<?php

declare(strict_types=1);

namespace App\Domain\Chat;

use App\Domain\Shared\NotFoundException;

class MessageNotFoundException extends NotFoundException
{
    public function __construct(string $messageId)
    {
        parent::__construct(\sprintf('Message with ID "%s" not found.', $messageId));
    }
}
