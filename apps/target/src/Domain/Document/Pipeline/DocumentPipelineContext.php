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
 * - `canonicalUrl`: normalised canonical URL resolved by the enrichment phase
 *   (Stage 0 of dedup). Stored on the context rather than on the document so
 *   downstream processors can read it without re-extracting.
 * - `rawHtml`: optional raw HTML payload provided by collect connectors that
 *   have it on hand. Kept transient (no DB persistence) — the canonical URL
 *   enrichment is its main consumer.
 * - `collectTaskId` / `provider`: identity of the tentative collect that
 *   produced this document. Required by the deduplication processor to
 *   record a {@see \App\Domain\Document\Deduplication\DuplicateAttempt}
 *   on the matched original (axe A, ADR-2026-006). Stored on the context
 *   rather than recomputed downstream so the dedup processor stays
 *   independent of the collect-task gateway.
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
        public ?string $canonicalUrl = null,
        public ?string $rawHtml = null,
        public ?string $collectTaskId = null,
        public ?string $provider = null,
    ) {
    }

    public function withSignal(string $name, Signal $signal): self
    {
        return $this->with([
            'signals' => array_merge($this->signals, [
                $name => $signal,
            ]),
        ]);
    }

    public function withHalt(TranslatedText $reason): self
    {
        return $this->with([
            'isHalted' => true,
            'haltReason' => $reason,
        ]);
    }

    public function withDuplicateOf(string $duplicateOfDocumentId): self
    {
        return $this->with([
            'duplicateOf' => $duplicateOfDocumentId,
        ]);
    }

    public function withCanonicalUrl(string $canonicalUrl): self
    {
        return $this->with([
            'canonicalUrl' => $canonicalUrl,
        ]);
    }

    /**
     * Apply targeted overrides on top of the current property values.
     * Public `with*` builders dispatch through here so they only have to
     * name the field(s) they actually change — the rest is forwarded
     * verbatim. Adding a new context field only requires touching the
     * constructor and one line below.
     *
     * @param array{
     *     document?: Document,
     *     watchFile?: WatchFile,
     *     signals?: array<string, Signal>,
     *     isHalted?: bool,
     *     haltReason?: ?TranslatedText,
     *     duplicateOf?: ?string,
     *     canonicalUrl?: ?string,
     *     rawHtml?: ?string,
     *     collectTaskId?: ?string,
     *     provider?: ?string,
     * } $overrides
     */
    private function with(array $overrides): self
    {
        return new self(
            document: $overrides['document'] ?? $this->document,
            watchFile: $overrides['watchFile'] ?? $this->watchFile,
            signals: $overrides['signals'] ?? $this->signals,
            isHalted: $overrides['isHalted'] ?? $this->isHalted,
            haltReason: $overrides['haltReason'] ?? $this->haltReason,
            duplicateOf: $overrides['duplicateOf'] ?? $this->duplicateOf,
            canonicalUrl: $overrides['canonicalUrl'] ?? $this->canonicalUrl,
            rawHtml: $overrides['rawHtml'] ?? $this->rawHtml,
            collectTaskId: $overrides['collectTaskId'] ?? $this->collectTaskId,
            provider: $overrides['provider'] ?? $this->provider,
        );
    }
}
