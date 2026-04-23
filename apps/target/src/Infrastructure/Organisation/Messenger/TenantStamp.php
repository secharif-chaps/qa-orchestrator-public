<?php

declare(strict_types=1);

namespace App\Infrastructure\Organisation\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

class TenantStamp implements StampInterface
{
    public function __construct(
        private readonly string $organisationId,
        private readonly string $effectiveUserId,
        private readonly ?string $impersonatorId = null,
    ) {
    }

    public function getOrganisationId(): string
    {
        return $this->organisationId;
    }

    public function getEffectiveUserId(): string
    {
        return $this->effectiveUserId;
    }

    public function getImpersonatorId(): ?string
    {
        return $this->impersonatorId;
    }
}
