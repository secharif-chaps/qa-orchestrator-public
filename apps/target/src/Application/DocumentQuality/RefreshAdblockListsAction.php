<?php

declare(strict_types=1);

namespace App\Application\DocumentQuality;

/**
 * Dispatched on a daily schedule by `DefaultScheduleProvider`.
 * Triggers a refresh of the consolidated adblock domain list.
 */
readonly class RefreshAdblockListsAction
{
}
