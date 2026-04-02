<?php

declare(strict_types=1);

namespace App\Domain\Actor;

use App\Domain\Source\SourceStatus;

enum ActorStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function isActive(): bool
    {
        return self::ACTIVE === $this;
    }

    public function isInactive(): bool
    {
        return self::INACTIVE === $this;
    }

    public function toSourceStatus(): SourceStatus
    {
        return match ($this) {
            self::ACTIVE => SourceStatus::ACTIVE,
            self::INACTIVE => SourceStatus::AUTO_DISABLED,
        };
    }
}
