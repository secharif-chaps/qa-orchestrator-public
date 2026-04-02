<?php

declare(strict_types=1);

namespace App\Domain\UsageLimit;

use App\Domain\UsageLimit\Exception\InvalidQuotaLimitException;

readonly class QuotaLimit
{
    private function __construct(
        private ?int $limit,
        private bool $inclusive = false,
    ) {
    }

    public static function fromNullableInt(?int $limit, bool $inclusive = false): self
    {
        if (null !== $limit && $limit < 0) {
            throw InvalidQuotaLimitException::negativeValue($limit);
        }

        return new self($limit, $inclusive);
    }

    public function isUnlimited(): bool
    {
        return null === $this->limit;
    }

    public function value(): ?int
    {
        return $this->limit;
    }

    public function allows(ResourceCount $resourceCount): bool
    {
        if ($this->isUnlimited()) {
            return true;
        }

        if ($this->inclusive) {
            return $resourceCount->value() <= $this->limit;
        }

        return $resourceCount->value() < $this->limit;
    }

    public function requiresLimitEnforcement(ResourceCount $resourceCount): bool
    {
        if ($this->isUnlimited()) {
            return false;
        }

        if ($this->inclusive) {
            return $resourceCount->value() > $this->limit;
        }

        return $resourceCount->value() >= $this->limit;
    }
}
