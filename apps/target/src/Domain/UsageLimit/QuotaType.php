<?php

declare(strict_types=1);

namespace App\Domain\UsageLimit;

enum QuotaType: string
{
    case WATCHFILE_MAX_OWNED_NON_ARCHIVED = 'watchfile_max_owned_non_archived';
    case WATCHFILE_MAX_ACTIVE_PER_USER = 'watchfile_max_active_per_user';
    case SOURCE_MAX_PER_WATCHFILE = 'source_max_per_watchfile';
    case SOURCE_MAX_ACTIVE_PER_WATCHFILE = 'source_max_active_per_watchfile';
    case ACTOR_MAX_PER_WATCHFILE = 'actor_max_per_watchfile';
    case DOCUMENT_MAX_PER_WATCHFILE = 'document_max_per_watchfile';

    /**
     * Returns the translation key for this quota type.
     *
     * @return string The translation key (e.g., 'quota.watchfile_max_owned_non_archived')
     */
    public function getTranslationKey(): string
    {
        return match ($this) {
            self::WATCHFILE_MAX_OWNED_NON_ARCHIVED => 'quota.watchfile_max_owned_non_archived',
            self::WATCHFILE_MAX_ACTIVE_PER_USER => 'quota.watchfile_max_active_per_user',
            self::SOURCE_MAX_PER_WATCHFILE => 'quota.source_max_per_watchfile',
            self::SOURCE_MAX_ACTIVE_PER_WATCHFILE => 'quota.source_max_active_per_watchfile',
            self::ACTOR_MAX_PER_WATCHFILE => 'quota.actor_max_per_watchfile',
            self::DOCUMENT_MAX_PER_WATCHFILE => 'quota.document_max_per_watchfile',
        };
    }
}
