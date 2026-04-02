<?php

declare(strict_types=1);

namespace App\Domain\Source;

enum SourceStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case AUTO_DISABLED = 'auto_disabled';

    public function isActive(): bool
    {
        return self::ACTIVE === $this;
    }

    public function isInactive(): bool
    {
        return self::INACTIVE === $this;
    }

    public function isAutoDisabled(): bool
    {
        return self::AUTO_DISABLED === $this;
    }
}
