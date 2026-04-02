<?php

declare(strict_types=1);

namespace App\Application\Actor;

use App\Application\SyncActionInterface;
use App\UserInterface\Dto\Actor\BatchChangeActorDataDto;

class BatchChangeActorStatusAction implements SyncActionInterface
{
    /**
     * @param BatchChangeActorDataDto[] $actors
     */
    public function __construct(
        public readonly array $actors,
    ) {
    }
}
