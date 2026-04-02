<?php

declare(strict_types=1);

namespace App\Domain\Logo;

interface LogoGatewayInterface
{
    public function getLogo(string $domain): Logo;

    public function supports(string $domain): bool;
}
