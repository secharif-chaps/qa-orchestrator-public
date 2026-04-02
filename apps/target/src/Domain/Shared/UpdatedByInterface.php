<?php

namespace App\Domain\Shared;

use App\Domain\User\User;

interface UpdatedByInterface
{
    public function getUpdatedBy(): ?User;

    public function setUpdatedBy(User $updatedBy): self;

    public function updateBy(User $updatedBy, ?\DateTimeImmutable $updatedAt = null): self;
}
