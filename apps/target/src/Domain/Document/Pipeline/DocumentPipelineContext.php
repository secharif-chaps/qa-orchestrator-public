<?php

declare(strict_types=1);

namespace App\Domain\Document\Pipeline;

use App\Domain\Document\Document;
use App\Domain\DocumentQuality\Signal;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;

/**
 * Neutral, immutable context shared by every processor of a document pipeline.
 *
 * It carries the document under inspection, references to the watchfile, the
 * accumulated quality signals (filled by scoring processors), and a few
 * cross-cutting flags consumed by orchestrators:
 *
 * - `isHalted` / `haltReason`: a processor decided the pipeline must stop
 *   early. Halt is intentionally neutral — the surrounding handler is the
 *   one who maps it onto a domain decision (e.g. `QualityDecision::REJECTED`
 *   or a duplicate-detection outcome). The reason is a `TranslatedText` so
 *   it can be displayed verbatim in any UI language.
 * - `duplicateOf`: the canonical id of an already-indexed document this one
 *   duplicates. Set by deduplication processors during the pre-save phase to
 *   instruct the ingest handler to skip the save.
 */
readonly class DocumentPipelineContext
{
    /**
     * @param array<string, Signal> $signals
     */
    public function __construct(
        public Document $document,
        public WatchFile $watchFile,
        public array $signals = [],
        public bool $isHalted = false,
        public ?TranslatedText $haltReason = null,
        public ?string $duplicateOf = null,
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
            isHalted: $this->isHalted,
            haltReason: $this->haltReason,
            duplicateOf: $this->duplicateOf,
        );
    }

    public function withHalt(TranslatedText $reason): self
    {
        return new self(
            document: $this->document,
            watchFile: $this->watchFile,
            signals: $this->signals,
            isHalted: true,
            haltReason: $reason,
            duplicateOf: $this->duplicateOf,
        );
    }

    public function withDuplicateOf(string $duplicateOfDocumentId): self
    {
        return new self(
            document: $this->document,
            watchFile: $this->watchFile,
            signals: $this->signals,
            isHalted: $this->isHalted,
            haltReason: $this->haltReason,
            duplicateOf: $duplicateOfDocumentId,
        );
    }
}
