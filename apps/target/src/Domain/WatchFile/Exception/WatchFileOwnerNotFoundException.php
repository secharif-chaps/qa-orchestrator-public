<?php

declare(strict_types=1);

namespace App\Domain\WatchFile\Exception;

use App\Domain\Shared\DomainException;

class WatchFileOwnerNotFoundException extends DomainException
{
    public function __construct(string $watchFileId)
    {
        parent::__construct(\sprintf(
            'WatchFile "%s" has no owner (no WatchFileUser with OWNER role and no createdBy)',
            $watchFileId
        ));
    }
}
