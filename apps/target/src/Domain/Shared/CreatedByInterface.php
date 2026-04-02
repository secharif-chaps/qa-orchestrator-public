<?php

namespace App\Domain\Shared;

use App\Domain\User\User;

interface CreatedByInterface
{
    public function getCreatedBy(): ?User;

    public function setCreatedBy(User $createdBy): self;
}
