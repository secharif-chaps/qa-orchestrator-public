<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure\Test;

use Symfony\Component\Mercure\Jwt\TokenProviderInterface;

/**
 * @internal should use only in tests
 *
 * A null implementation of TokenProviderInterface for testing purposes
 */
readonly class NullTokenProvider implements TokenProviderInterface
{
    public function getJwt(): string
    {
        return 'null-token';
    }
}
