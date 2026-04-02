<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Source;

use App\Application\SyncActionInterface;
use App\Domain\Source\SourceStatus;
use App\Domain\User\User;

final readonly class ChangeSourceStatusAction implements SyncActionInterface
{
    public function __construct(
        public ?string $watchFileId,
        public string $sourceId,
        public SourceStatus $status,
        public ?User $user = null,
    ) {
    }
}
