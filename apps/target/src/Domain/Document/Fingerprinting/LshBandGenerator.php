<?php

declare(strict_types=1);

namespace App\Domain\Document\Fingerprinting;

/**
 * Splits a 128-value MinHash signature into 32 bands of 4 rows and
 * hashes each band. Two documents that collide on **any** band are
 * candidates for a fine-grained Jaccard check.
 *
 * Choice of 32 × 4 (S-curve threshold ≈ 0.74):
 *   - P(candidate | Jaccard = 0.80) ≈ 97 %
 *   - P(candidate | Jaccard = 0.50) ≈ 18 %
 *
 * @see ADR-2026-006 — LSH Band Hash Generation.
 */
readonly class LshBandGenerator
{
    public const int NUM_BANDS = 32;
    public const int ROWS_PER_BAND = MinHashGenerator::NUM_HASHES / self::NUM_BANDS; // 4

    /**
     * Hash each band of the MinHash signature.
     *
     * MD5 is intentional and **not** crypto here — we just need a fast
     * fixed-width digest with low practical collision over short input
     * (4 ints separated by `':'`). The output is used as an OpenSearch
     * keyword for bucket lookup.
     *
     * @param list<int> $minHashSignature 128 values from MinHashGenerator
     *
     * @return list<string> 32 lowercase 32-char hex digests
     */
    public function generate(array $minHashSignature): array
    {
        $expected = MinHashGenerator::NUM_HASHES;
        if (\count($minHashSignature) !== $expected) {
            throw new \InvalidArgumentException(\sprintf(
                'MinHash signature must contain exactly %d values, got %d.',
                $expected,
                \count($minHashSignature),
            ));
        }

        $bandHashes = [];
        for ($band = 0; $band < self::NUM_BANDS; ++$band) {
            $start = $band * self::ROWS_PER_BAND;
            $bandValues = \array_slice($minHashSignature, $start, self::ROWS_PER_BAND);
            $bandHashes[] = md5(implode(':', $bandValues));
        }

        return $bandHashes;
    }
}
