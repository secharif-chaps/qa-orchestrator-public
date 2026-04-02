<?php

declare(strict_types=1);

namespace App\Domain\Collect\Exception;

class CollectTaskTokenClaimsInvalidException extends CollectException
{
    public static function invalidType(): self
    {
        return new self('Invalid collect task token: Claims must be strings.');
    }
}
