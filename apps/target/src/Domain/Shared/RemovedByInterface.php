<?php

namespace App\Domain\Shared;

use App\Domain\User\User;

interface RemovedByInterface
{
    public function getRemovedBy(): ?User;

    public function remove(User $removedBy): self;
}
