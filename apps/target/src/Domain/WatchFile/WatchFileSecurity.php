<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

/**
 * Security attributes for WatchFile access control.
 *
 * These constants define the security attributes used in API Platform
 * security expressions and are checked by the WatchFileVoter.
 */
final class WatchFileSecurity
{
    /**
     * Attribute to check if user has edit permissions on a WatchFile.
     * Grants access to users with OWNER or EDITOR role.
     */
    public const string EDIT = 'WATCH_FILE_EDIT';

    /**
     * Attribute to check if user has view permissions on a WatchFile.
     * Grants access to users with OWNER, EDITOR, or VIEWER role.
     */
    public const string VIEW = 'WATCH_FILE_VIEW';

    /**
     * Security expression for API Platform - requires EDIT permission.
     */
    public const string SECURITY_EDIT = 'is_granted("WATCH_FILE_EDIT", object)';

    /**
     * Security expression for API Platform - requires VIEW permission.
     */
    public const string SECURITY_VIEW = 'is_granted("WATCH_FILE_VIEW", object)';
}
