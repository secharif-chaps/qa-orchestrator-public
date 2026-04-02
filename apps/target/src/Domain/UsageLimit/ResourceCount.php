<?php

declare(strict_types=1);

namespace App\Domain\UsageLimit;

use App\Domain\UsageLimit\Exception\InvalidResourceCountException;

readonly class ResourceCount
{
    private function __construct(
        private int $value,
    ) {
    }

    public static function fromInt(int $value): self
    {
        if ($value < 0) {
            throw InvalidResourceCountException::negativeValue($value);
        }

        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function exceeds(QuotaLimit $limit): bool
    {
        return $limit->requiresLimitEnforcement($this);
    }
}
