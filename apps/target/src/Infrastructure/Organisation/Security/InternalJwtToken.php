<?php

declare(strict_types=1);

namespace App\Infrastructure\Organisation\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

class InternalJwtToken extends PostAuthenticationToken
{
    /**
     * @param array<string, mixed> $claims
     * @param list<string>         $roleNames
     */
    public function __construct(
        UserInterface $user,
        string $firewallName,
        array $roleNames,
        private readonly array $claims,
    ) {
        parent::__construct($user, $firewallName, $roleNames);
    }

    /**
     * @return array<string, mixed>
     */
    public function getClaims(): array
    {
        return $this->claims;
    }
}
