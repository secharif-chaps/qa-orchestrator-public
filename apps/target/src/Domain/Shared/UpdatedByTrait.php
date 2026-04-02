<?php

namespace App\Domain\Shared;

use App\Domain\User\User;

/**
 * @property ?User $updatedBy
 */
trait UpdatedByTrait
{
    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function updateBy(User $updatedBy, ?\DateTimeImmutable $updatedAt = null): self
    {
        $this->setUpdatedBy($updatedBy);

        /* @phpstan-ignore-next-line function.alreadyNarrowedType */
        if (method_exists($this, 'setUpdatedAt')) {
            if (null === $updatedAt) {
                $updatedAt = new \DateTimeImmutable();
            }

            $this->setUpdatedAt($updatedAt);
        }

        return $this;
    }
}
