<?php

declare(strict_types=1);

namespace App\Domain\Collect\Exception;

use App\Domain\Shared\NotFoundException;

class CollectTaskNotFoundException extends NotFoundException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('CollectTask with ID "%s" not found', $id));
    }

    public static function withProviderTaskId(string $providerName, string $providerTaskId): self
    {
        return new self(\sprintf(
            'CollectTask with provider "%s" and task ID "%s" not found',
            $providerName,
            $providerTaskId
        ));
    }
}
