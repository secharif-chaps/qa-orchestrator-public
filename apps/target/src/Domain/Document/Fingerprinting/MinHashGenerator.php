<?php

declare(strict_types=1);

namespace App\Domain\Document\Fingerprinting;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Generates a 128-value MinHash signature from a shingle set.
 *
 * Documents whose Jaccard similarity ≥ 0.5 produce signatures that
 * collide on a measurable proportion of slots — `count(sig_a[i] ===
 * sig_b[i]) / 128` is an unbiased estimator of Jaccard similarity (stage
 * 3 of the dedup pipeline; see ADR-2026-006).
 *
 * @see ADR-2026-006 — MinHash Signature Generation.
 */
readonly class MinHashGenerator
{
    /**
     * 128 hash functions: standard error ≈ 1/√128 ≈ 8.8% on Jaccard
     * estimates. Multiple of common LSH band sizes (32×4, 64×2, 16×8).
     */
    public const int NUM_HASHES = 128;

    /**
     * Mersenne prime 2^31 - 1. Co-prime with 2^32 so the linear hash
     * family `(a x + b) mod p` is well-distributed over 32-bit inputs
     * (CRC32 outputs).
     */
    private const int PRIME = 2147483647;

    /**
     * Deterministic seed for `(a, b)` coefficient generation. Changing
     * it would invalidate every persisted signature — treat as a
     * versioning constant.
     */
    private const int SEED = 42;

    /**
     * Pre-computed `(a, b)` pairs as GMP numbers so hot-path `gmp_*`
     * calls don't re-parse them on every shingle.
     *
     * @var list<array{a: \GMP, b: \GMP}>
     */
    private array $hashFunctions;
    private \GMP $prime;

    public function __construct()
    {
        $this->prime = gmp_init(self::PRIME);
        $this->hashFunctions = $this->initializeHashFunctions();
    }

    /**
     * Build the 128 minimum hash values for the given shingle set.
     *
     * @param iterable<string> $shingles
     *
     * @return list<int> 128 unsigned 32-bit integers
     */
    public function generate(iterable $shingles): array
    {
        $signature = array_fill(0, self::NUM_HASHES, \PHP_INT_MAX);
        $hasShingles = false;

        foreach ($shingles as $shingle) {
            $hasShingles = true;
            // crc32() returns a 32-bit unsigned value as int on 64-bit PHP.
            $shingleHash = gmp_init(crc32($shingle));

            for ($i = 0; $i < self::NUM_HASHES; ++$i) {
                $f = $this->hashFunctions[$i];
                $hashValue = gmp_intval(gmp_mod(gmp_add(gmp_mul($f['a'], $shingleHash), $f['b']), $this->prime));

                if ($hashValue < $signature[$i]) {
                    $signature[$i] = $hashValue;
                }
            }
        }

        // Empty input → conventional all-zero signature, distinguishable
        // from a real signature (which has values in [0, PRIME)).
        return $hasShingles ? $signature : array_fill(0, self::NUM_HASHES, 0);
    }

    /**
     * Build the `(a, b)` table.
     *
     * Uses an isolated `\Random\Randomizer` rather than `mt_srand(42)`
     * so we don't tamper with the global mt_rand state — the previous
     * `mt_srand(42); …; mt_srand();` sketch from the ADR would have
     * silently de-randomised any concurrent code path that read from
     * `mt_rand()` while the generator was being constructed.
     *
     * @return list<array{a: \GMP, b: \GMP}>
     */
    private function initializeHashFunctions(): array
    {
        $randomizer = new Randomizer(new Mt19937(self::SEED));
        $functions = [];

        for ($i = 0; $i < self::NUM_HASHES; ++$i) {
            $functions[] = [
                'a' => gmp_init($randomizer->getInt(1, self::PRIME - 1)),
                'b' => gmp_init($randomizer->getInt(0, self::PRIME - 1)),
            ];
        }

        return $functions;
    }
}
