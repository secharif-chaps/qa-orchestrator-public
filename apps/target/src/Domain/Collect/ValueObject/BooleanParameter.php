<?php

namespace App\Domain\Collect\ValueObject;

readonly class BooleanParameter extends Parameter
{
    public function isNullable(): bool
    {
        return false === $this->required && null === $this->default;
    }
}
