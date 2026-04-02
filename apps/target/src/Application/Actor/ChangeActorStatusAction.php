<?php

declare(strict_types=1);

namespace App\Application\Actor;

use App\Application\SyncActionInterface;
use App\Domain\Actor\ActorStatus;
use App\Domain\User\User;

readonly class ChangeActorStatusAction implements SyncActionInterface
{
    public function __construct(
        public string $watchFileId,
        public string $actorId,
        /** @var string[] */
        public array $sourceIds,
        public ActorStatus $newStatus,
        public User $user,
    ) {
    }
}
