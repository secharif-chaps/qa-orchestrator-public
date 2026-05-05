<?php

declare(strict_types=1);

namespace App\Domain\Document\Deduplication;

/**
 * Outcome of a {@see DuplicateDetectorInterface::detect()} call
 * (ADR-2026-006). Built only via {@see unique()} or {@see match()}; the
 * factories enforce that match metadata (stage, originalDocumentId,
 * similarity) is present iff the outcome is non-UNIQUE.
 */
readonly class DuplicateResult
{
    private function __construct(
        public DuplicateOutcome $outcome,
        public ?DuplicateMatchStage $stage = null,
        public ?string $originalDocumentId = null,
        public ?float $similarity = null,
    ) {
    }

    public static function unique(): self
    {
        return new self(DuplicateOutcome::UNIQUE);
    }

    public static function match(
        DuplicateOutcome $outcome,
        DuplicateMatchStage $stage,
        string $originalDocumentId,
        float $similarity,
    ): self {
        if (DuplicateOutcome::UNIQUE === $outcome) {
            throw new \InvalidArgumentException('Use DuplicateResult::unique() for the UNIQUE outcome.');
        }

        return new self($outcome, $stage, $originalDocumentId, $similarity);
    }

    public function isMatch(): bool
    {
        return DuplicateOutcome::UNIQUE !== $this->outcome;
    }
}
