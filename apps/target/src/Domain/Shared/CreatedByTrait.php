<?php

namespace App\Domain\Shared;

use App\Domain\User\User;

/**
 * @property ?User $createdBy
 */
trait CreatedByTrait
{
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): CreatedByInterface
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}
