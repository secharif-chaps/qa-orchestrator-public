<?php

declare(strict_types=1);

namespace App\Domain\Document\Deduplication;

use App\Domain\Document\Fingerprinting\Fingerprint;
use App\Domain\Document\Fingerprinting\MinHashGenerator;

/**
 * Pure-domain similarity helpers for {@see Fingerprint} pairs (ADR-2026-006).
 *
 * Kept off the {@see Fingerprint} VO on purpose: the value object stays a
 * pluggable bundle of bytes that round-trips through OpenSearch; the
 * comparison logic is a domain *service* that operates on two of them.
 */
class FingerprintSimilarity
{
    private const int SIMHASH_BITS = 64;

    /**
     * Hamming distance between two SimHash digests (number of differing
     * bits, 0..64). XOR + popcount via GMP — both inputs are 16-char hex
     * strings produced by {@see \App\Domain\Document\Fingerprinting\SimHashGenerator}.
     */
    public function hamming(Fingerprint $a, Fingerprint $b): int
    {
        return gmp_popcount(gmp_xor(gmp_init($a->simHash, 16), gmp_init($b->simHash, 16)));
    }

    /**
     * Map a Hamming distance to an approximate similarity ratio in [0, 1].
     * Each differing bit reduces similarity by 1/64.
     */
    public function hammingToSimilarity(int $hammingDistance): float
    {
        return 1.0 - ($hammingDistance / self::SIMHASH_BITS);
    }

    /**
     * Estimated Jaccard similarity from two MinHash signatures: the
     * proportion of slots where both signatures collide. Unbiased estimator
     * with standard error ≈ 1/√128 ≈ 8.8 % at the configured signature
     * length.
     */
    public function jaccard(Fingerprint $a, Fingerprint $b): float
    {
        $matches = 0;
        for ($i = 0; $i < MinHashGenerator::NUM_HASHES; ++$i) {
            if ($a->minHashSignature[$i] === $b->minHashSignature[$i]) {
                ++$matches;
            }
        }

        return $matches / MinHashGenerator::NUM_HASHES;
    }

    /**
     * Exact set-based Jaccard similarity for the title-fallback stage:
     * `|A ∩ B| / |A ∪ B|` on raw shingle lists. Unlike {@see jaccard()}
     * (MinHash-estimated), this is computed directly on the small shingle
     * sets stored in `Fingerprint::$titleShingles` — typically a handful
     * of entries per title, so a hash-set intersection is cheaper than
     * MinHash machinery.
     *
     * Returns 0.0 when either side is empty so the caller can use this as
     * a safe `>= threshold` gate.
     */
    public function titleShingleJaccard(Fingerprint $a, Fingerprint $b): float
    {
        if ([] === $a->titleShingles || [] === $b->titleShingles) {
            return 0.0;
        }

        $aSet = array_fill_keys($a->titleShingles, true);
        $bSet = array_fill_keys($b->titleShingles, true);

        $intersection = \count(array_intersect_key($aSet, $bSet));
        $union = \count($aSet) + \count($bSet) - $intersection;

        return 0 === $union ? 0.0 : $intersection / $union;
    }
}
