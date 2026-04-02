<?php

declare(strict_types=1);

namespace App\Domain\Logo;

use App\Domain\Shared\NotFoundException;

class LogoNotFoundException extends NotFoundException
{
    public function __construct(string $domain, ?\Throwable $previous = null)
    {
        parent::__construct(
            \sprintf('Logo not found for domain: %s', $domain),
            0,
            $previous
        );
    }
}
