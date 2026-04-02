<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

use App\Domain\Document\Document;
use App\Domain\WatchFile\WatchFile;

readonly class ProcessingContext
{
    /**
     * @param array<string, Signal> $signals
     */
    public function __construct(
        public Document $document,
        public WatchFile $watchFile,
        public array $signals = [],
        public ?QualityDecision $earlyDecision = null,
        public ?string $earlyDecisionReason = null,
    ) {
    }

    public function withSignal(string $name, Signal $signal): self
    {
        return new self(
            document: $this->document,
            watchFile: $this->watchFile,
            signals: array_merge($this->signals, [
                $name => $signal,
            ]),
            earlyDecision: $this->earlyDecision,
            earlyDecisionReason: $this->earlyDecisionReason,
        );
    }

    public function withEarlyDecision(QualityDecision $decision, string $reason): self
    {
        return new self(
            document: $this->document,
            watchFile: $this->watchFile,
            signals: $this->signals,
            earlyDecision: $decision,
            earlyDecisionReason: $reason,
        );
    }

    public function hasEarlyDecision(): bool
    {
        return null !== $this->earlyDecision;
    }
}
