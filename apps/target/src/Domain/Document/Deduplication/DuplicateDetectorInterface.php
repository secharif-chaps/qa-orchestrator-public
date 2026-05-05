<?php

declare(strict_types=1);

namespace App\Domain\Document\Deduplication;

use App\Domain\Document\Fingerprinting\Fingerprint;

/**
 * Port for the dedup pipeline orchestrator (ADR-2026-006). Runs the four
 * stages in cheapest-first order, short-circuiting on the first match:
 *
 *   0. Canonical URL (~2ms keyword lookup)
 *   1. SHA256 content hash (~2ms keyword lookup)
 *   2. SimHash exact match (~5ms keyword lookup)
 *   3. LSH candidates → Hamming on SimHash → Jaccard on MinHash (~50ms)
 *
 * The detector is read-only — it returns a {@see DuplicateResult}. The
 * caller (typically a save-pipeline processor) is responsible for
 * recording a {@see DuplicateAttempt} on the matched original document
 * and for the reject/flag decision (axe A of TAR-1146; axe B is reported
 * out of MVP scope).
 */
interface DuplicateDetectorInterface
{
    /**
     * @param Fingerprint|null $fingerprint       the candidate's fingerprint when its content
     *                                            is non-empty; pass null to run only stage 0
     *                                            (canonical-URL probe) — useful for documents
     *                                            that carry a canonical tag but no body
     * @param string|null      $canonicalUrl      the candidate's canonical URL if known (stage 0)
     * @param string|null      $excludeDocumentId document id to ignore — used when re-indexing
     *                                            an existing document so it doesn't match itself
     */
    public function detect(
        ?Fingerprint $fingerprint = null,
        ?string $canonicalUrl = null,
        ?string $excludeDocumentId = null,
    ): DuplicateResult;
}
