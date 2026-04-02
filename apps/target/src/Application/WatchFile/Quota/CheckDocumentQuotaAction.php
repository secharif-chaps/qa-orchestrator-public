<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Quota;

use App\Application\SyncActionInterface;

/**
 * This action triggers a check of all WatchFiles to identify those that exceed the configured document quota limit. WatchFiles exceeding the limit are automatically moved to DRAFT status.
 *
 * @param int|null $quota Optional quota override. If null, uses the quota from configuration.
 */
readonly class CheckDocumentQuotaAction implements SyncActionInterface
{
    public function __construct(
        public ?int $quota = null,
    ) {
    }
}
