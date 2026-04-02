<?php

declare(strict_types=1);

namespace App\Domain\Organisation;

use App\Domain\User\User;

class TenantContext
{
    private ?Organisation $organisation = null;
    private ?User $effectiveUser = null;
    private ?User $impersonator = null;

    public function set(Organisation $organisation, User $effectiveUser, ?User $impersonator = null): void
    {
        $this->organisation = $organisation;
        $this->effectiveUser = $effectiveUser;
        $this->impersonator = $impersonator;
    }

    public function getCurrentOrganisation(): Organisation
    {
        if (null === $this->organisation) {
            throw new NoTenantContextException(
                'Tenant context has not been set. Ensure the request has been processed by TenantContextListener.'
            );
        }

        return $this->organisation;
    }

    public function getEffectiveUser(): User
    {
        if (null === $this->effectiveUser) {
            throw new NoTenantContextException(
                'Tenant context has not been set. Ensure the request has been processed by TenantContextListener.'
            );
        }

        return $this->effectiveUser;
    }

    public function isInitialized(): bool
    {
        return null !== $this->organisation;
    }

    public function isImpersonating(): bool
    {
        return null !== $this->impersonator;
    }

    public function getImpersonator(): ?User
    {
        return $this->impersonator;
    }

    public function getAuditUser(): User
    {
        if (null !== $this->impersonator) {
            return $this->impersonator;
        }

        return $this->getEffectiveUser();
    }
}
