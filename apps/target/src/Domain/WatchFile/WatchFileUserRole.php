<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

enum WatchFileUserRole: string
{
    case OWNER = 'owner';
    case EDITOR = 'editor';
    case VIEWER = 'viewer';

    /** @var list<WatchFileUserRole> */
    public const array ROLE_CAN_RECEIVE_REAL_TIME_UPDATES = [self::OWNER, self::EDITOR];

    public function canReceiveRealTimeUpdates(): bool
    {
        return \in_array($this, self::ROLE_CAN_RECEIVE_REAL_TIME_UPDATES, true);
    }

    public static function create(string $role): self
    {
        return self::tryFrom($role) ?? throw new \InvalidArgumentException('Invalid role');
    }

    /**
     * @return list<string>
     */
    public static function editableValues(): array
    {
        $cases = array_filter(self::cases(), fn (WatchFileUserRole $role) => self::OWNER !== $role);

        return array_column($cases, 'value');
    }

    /**
     * @return list<string>
     */
    public static function allValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
