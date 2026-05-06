<?php

namespace App\Domain\WatchFile\Exception;

use App\Domain\Shared\DomainException;
use App\Domain\WatchFile\WatchFileUser;

class CannotRemoveOwnerException extends DomainException
{
    public function __construct(WatchFileUser $watchFileUser)
    {
        parent::__construct(\sprintf(
            'The watchfile user "%s" is the owner and cannot be removed.',
            $watchFileUser->getId()
        ));
    }
}
