<?php

declare(strict_types=1);

namespace App\Domain\Document\Fingerprinting;

/**
 * Generates a 64-bit SimHash from a shingle set.
 *
 * Documents that share most shingles produce SimHashes whose Hamming
 * distance is small — this is what stage 2 of the dedup pipeline (see
 * ADR-2026-006) consumes via `gmp_xor()` + `gmp_popcount()`.
 *
 * Output is a canonical lowercase 16-character hexadecimal string so
 * fingerprints round-trip safely through OpenSearch keyword fields and
 * cross-instance equality is byte-stable.
 *
 * @see ADR-2026-006 — SimHash Generation.
 */
readonly class SimHashGenerator
{
    private const int HASH_BITS = 64;
    private const int HASH_BYTES = self::HASH_BITS / 8;

    /**
     * Generate the 64-bit SimHash of a shingle set.
     *
     * Algorithm (Charikar):
     *   1. 64 signed counters initialised to zero.
     *   2. Each shingle is hashed to 8 bytes via xxh3 (fast non-crypto).
     *   3. Bit i of the hash flips counter i by +1 or -1.
     *   4. Final hash bit i = 1 iff counter i > 0.
     *
     * Implementation detail — we read bits straight off the binary
     * `xxh3` digest rather than going through `gmp_intval(gmp_init($hex,
     * 16))` (suggested by the ADR sketch). That intermediate path silently
     * truncates / sign-extends when the high bit is set on PHP 64-bit
     * builds, and `>>` on a signed int does an arithmetic shift — both
     * subtle ways to corrupt half the hash space.
     *
     * @param iterable<string> $shingles
     *
     * @return string 16-char lowercase hex (64 bits)
     */
    public function generate(iterable $shingles): string
    {
        $bitCounts = array_fill(0, self::HASH_BITS, 0);
        $hasShingles = false;

        foreach ($shingles as $shingle) {
            $hasShingles = true;
            /** @var array<int, int> $bytes 1-indexed array of 8 unsigned bytes */
            $bytes = unpack('C*', hash('xxh3', $shingle, true));

            for ($byteIdx = 0; $byteIdx < self::HASH_BYTES; ++$byteIdx) {
                $byte = $bytes[$byteIdx + 1];
                for ($bit = 0; $bit < 8; ++$bit) {
                    $bitIndex = $byteIdx * 8 + $bit;
                    if (0 !== ($byte & (1 << $bit))) {
                        ++$bitCounts[$bitIndex];
                    } else {
                        --$bitCounts[$bitIndex];
                    }
                }
            }
        }

        if (!$hasShingles) {
            return str_repeat('0', 16);
        }

        $simHash = gmp_init(0);
        for ($i = 0; $i < self::HASH_BITS; ++$i) {
            if ($bitCounts[$i] > 0) {
                // `gmp_setbit` mutates `$simHash` in place and returns
                // null — never assign its result.
                gmp_setbit($simHash, $i);
            }
        }

        return str_pad(gmp_strval($simHash, 16), 16, '0', \STR_PAD_LEFT);
    }
}
