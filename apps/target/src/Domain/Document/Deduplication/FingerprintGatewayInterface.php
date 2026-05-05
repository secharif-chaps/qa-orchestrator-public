<?php

declare(strict_types=1);

namespace App\Domain\Document\Deduplication;

use App\Domain\Document\Document;
use App\Domain\Document\Fingerprinting\Fingerprint;

/**
 * Read-side gateway for the dedup pipeline's three fingerprint-based
 * stages (ADR-2026-006). Stage 0 (canonical URL) lives on
 * {@see \App\Domain\Document\DocumentGatewayInterface::findByCanonicalUrl()}.
 *
 * The write side is intentionally narrow: callers persist a complete
 * {@see Fingerprint} bundle on a `Document` via
 * {@see \App\Domain\Document\DocumentGatewayInterface::save()} — there is
 * no separate index. This interface is read-only.
 */
interface FingerprintGatewayInterface
{
    /**
     * Stage 1 — exact content match via SHA256 of the normalised text.
     * Returns the first matching document (any) or null. Scoping by
     * watch file is the caller's responsibility.
     */
    public function findByContentHash(string $contentHash, ?string $excludeDocumentId = null): ?Document;

    /**
     * Stage 2 — exact SimHash match. Hamming-distance scanning for
     * "≤ N bits flipped" is performed by the caller against the
     * candidates returned here (a strict equality lookup is cheaper
     * than a script query).
     *
     * @return list<Document>
     */
    public function findBySimHash(string $simHash, ?string $excludeDocumentId = null): array;

    /**
     * Stage 3 — LSH candidate retrieval. Returns documents whose
     * fingerprint shares **at least one** band with the provided list.
     * The caller verifies similarity by computing Jaccard on the
     * candidates' MinHash signatures.
     *
     * @param list<string> $bandHashes 32 × 32-char hex digests from {@see \App\Domain\Document\Fingerprinting\LshBandGenerator}
     *
     * @return list<Document>
     */
    public function findByLshBands(array $bandHashes, ?string $excludeDocumentId = null, int $limit = 50): array;
}
