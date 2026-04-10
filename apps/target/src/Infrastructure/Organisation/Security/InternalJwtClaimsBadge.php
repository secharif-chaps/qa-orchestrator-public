<?php

declare(strict_types=1);

namespace App\Infrastructure\Organisation\Security;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

/**
 * Carries decoded JWT claims through the Symfony authentication pipeline.
 */
class InternalJwtClaimsBadge implements BadgeInterface
{
    /**
     * @param array<string, mixed> $claims
     */
    public function __construct(
        private readonly array $claims,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getClaims(): array
    {
        return $this->claims;
    }

    public function isResolved(): bool
    {
        return true;
    }
}
