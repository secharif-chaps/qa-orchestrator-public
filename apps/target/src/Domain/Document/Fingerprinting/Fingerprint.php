<?php

declare(strict_types=1);

namespace App\Domain\Document\Fingerprinting;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Bundle of fingerprint values computed for a document, consumed by the
 * dedup pipeline (ADR-2026-006):
 *
 * - {@see $contentHash}      — Stage 1 SHA256 of the normalised text
 * - {@see $simHash}          — Stage 2 SimHash 64-bit, hex-encoded (16 chars)
 * - {@see $minHashSignature} — Stage 3 MinHash signature (128 unsigned 32-bit ints)
 * - {@see $lshBands}         — Stage 3 LSH band hashes (32 × 32-char hex digests)
 * - {@see $titleShingles}    — Stage 4 raw title shingles (k-shingles of the
 *                              normalised title). Empty when the document
 *                              has no title or stage 4 is disabled.
 *
 * Either every body value is set (a complete fingerprint) or every body
 * value is absent (e.g. a document indexed before the dedup feature
 * shipped). Partial bundles are rejected at construction. The title
 * shingles are independent — present or absent on their own merit.
 *
 * The `document:save` group annotations propagate the OpenSearch
 * serialization context from `Document` so the nested object lands on the
 * indexed payload (and round-trips back via `DocumentDenormalizer`).
 */
readonly class Fingerprint
{
    /**
     * @param list<int>    $minHashSignature 128 unsigned 32-bit minimums
     * @param list<string> $lshBands         32 × 32-char hex digests
     * @param list<string> $titleShingles    Raw shingles of the normalised title
     */
    public function __construct(
        #[Groups(['document:save'])]
        public string $contentHash,
        #[Groups(['document:save'])]
        public string $simHash,
        #[Groups(['document:save'])]
        public array $minHashSignature,
        #[Groups(['document:save'])]
        public array $lshBands,
        #[Groups(['document:save'])]
        public array $titleShingles = [],
    ) {
        if (MinHashGenerator::NUM_HASHES !== \count($minHashSignature)) {
            throw new \InvalidArgumentException(\sprintf(
                'minHashSignature must contain exactly %d values, got %d.',
                MinHashGenerator::NUM_HASHES,
                \count($minHashSignature),
            ));
        }

        if (LshBandGenerator::NUM_BANDS !== \count($lshBands)) {
            throw new \InvalidArgumentException(\sprintf(
                'lshBands must contain exactly %d band hashes, got %d.',
                LshBandGenerator::NUM_BANDS,
                \count($lshBands),
            ));
        }
    }
}
