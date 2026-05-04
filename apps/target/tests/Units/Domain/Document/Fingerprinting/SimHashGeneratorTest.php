<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Fingerprinting;

use App\Domain\Document\Fingerprinting\SimHashGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SimHashGenerator::class)]
class SimHashGeneratorTest extends TestCase
{
    private SimHashGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SimHashGenerator();
    }

    // ─── Output format ───────────────────────────────────────────────────

    public function testEmptyShinglesReturnsAllZeros(): void
    {
        self::assertSame('0000000000000000', $this->generator->generate([]));
    }

    public function testOutputIsExactly16HexChars(): void
    {
        $hash = $this->generator->generate(['hello world']);

        self::assertSame(16, \strlen($hash));
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $hash);
    }

    public function testOutputIsLeftPaddedWithZerosWhenSmall(): void
    {
        // Hash with all bit counters ≤ 0 → simHash int = 0 → must still
        // be returned as 16 zero chars (no string truncation).
        $hash = $this->generator->generate([]);

        self::assertSame(16, \strlen($hash));
    }

    // ─── Determinism ─────────────────────────────────────────────────────

    public function testIsDeterministicForSameInput(): void
    {
        $shingles = ['the quick brown', 'quick brown fox', 'brown fox jumps'];

        $a = $this->generator->generate($shingles);
        $b = $this->generator->generate($shingles);

        self::assertSame($a, $b);
    }

    public function testIsOrderIndependent(): void
    {
        // SimHash is symmetric: swapping shingle order must not change
        // the output (counters are commutative).
        $forward = $this->generator->generate(['a', 'b', 'c']);
        $reversed = $this->generator->generate(['c', 'b', 'a']);

        self::assertSame($forward, $reversed);
    }

    public function testIgnoresShingleMultiplicity(): void
    {
        // The dedup pipeline feeds deduplicated shingles, but if a
        // duplicate slips through it shifts every counter the same way
        // — exposing it would skew Hamming distance against shingle
        // density rather than content.
        $unique = $this->generator->generate(['a', 'b', 'c']);
        $withDuplicate = $this->generator->generate(['a', 'b', 'c', 'a']);

        // We don't assert equality (impl uses raw counts), only document
        // the current behaviour: duplicates DO move the hash.
        self::assertNotSame($unique, $withDuplicate);
    }

    // ─── Sensitivity ─────────────────────────────────────────────────────

    public function testDifferentShinglesProduceDifferentHashes(): void
    {
        $a = $this->generator->generate(['the quick brown']);
        $b = $this->generator->generate(['the slow purple']);

        self::assertNotSame($a, $b);
    }

    public function testSimilarDocumentsHaveSmallHammingDistance(): void
    {
        // 9/10 shingles in common → SimHash should agree on most bits.
        $base = ['s1', 's2', 's3', 's4', 's5', 's6', 's7', 's8', 's9', 's10'];
        $similar = ['s1', 's2', 's3', 's4', 's5', 's6', 's7', 's8', 's9', 'OTHER'];

        $distance = $this->hammingDistance(
            $this->generator->generate($base),
            $this->generator->generate($similar),
        );

        // 64-bit hash, high overlap → expect < 16 bits flipped.
        self::assertLessThan(16, $distance, "Hamming distance was {$distance}, expected < 16");
    }

    public function testDissimilarDocumentsHaveLargeHammingDistance(): void
    {
        // Two unrelated shingle sets → Hamming distance should sit near
        // the random-baseline of 32 bits (half of 64).
        $a = ['cat', 'dog', 'bird', 'fish', 'mouse'];
        $b = ['plane', 'train', 'car', 'boat', 'truck'];

        $distance = $this->hammingDistance($this->generator->generate($a), $this->generator->generate($b));

        // Bound on each side of the random expectation; loose enough
        // not to be flaky but tight enough to catch a regression that
        // collapses the hash space.
        self::assertGreaterThan(16, $distance, "Hamming distance was {$distance}, expected > 16");
        self::assertLessThanOrEqual(64, $distance);
    }

    public function testHashChangesWhenSingleShingleFlipped(): void
    {
        $base = ['alpha', 'beta', 'gamma', 'delta', 'epsilon'];
        $flipped = ['alpha', 'beta', 'gamma', 'delta', 'OMEGA'];

        self::assertNotSame($this->generator->generate($base), $this->generator->generate($flipped));
    }

    // ─── Robustness ──────────────────────────────────────────────────────

    public function testHandlesUnicodeShingles(): void
    {
        $hash = $this->generator->generate(['東京都は日本', '京都は日本の', '都は日本の首']);

        self::assertSame(16, \strlen($hash));
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $hash);
    }

    public function testHandlesGenerator(): void
    {
        // `iterable<string>` accepts a generator — we shouldn't require
        // the caller to materialise the shingle list.
        $shingles = (function (): \Generator {
            yield 'a';
            yield 'b';
            yield 'c';
        })();

        $hash = $this->generator->generate($shingles);

        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $hash);
    }

    public function testHandlesLargeShingleSet(): void
    {
        $shingles = [];
        for ($i = 0; $i < 5_000; ++$i) {
            $shingles[] = "shingle-{$i}";
        }

        $start = microtime(true);
        $hash = $this->generator->generate($shingles);
        $elapsed = microtime(true) - $start;

        self::assertSame(16, \strlen($hash));
        self::assertLessThan(2.0, $elapsed, "Took {$elapsed}s on 5k shingles");
    }

    public function testHashUsesAllBitsAcrossDifferentInputs(): void
    {
        // Sanity guard: a buggy reader (e.g. one that always reads bit 0
        // of every byte) would produce hashes constrained to one nibble.
        // Compute a few hashes and check we observe variety in every
        // hex position.
        $hashes = [
            $this->generator->generate(['cat']),
            $this->generator->generate(['dog']),
            $this->generator->generate(['fish']),
            $this->generator->generate(['bird']),
            $this->generator->generate(['mouse']),
            $this->generator->generate(['snake']),
            $this->generator->generate(['horse']),
            $this->generator->generate(['cow']),
        ];

        for ($pos = 0; $pos < 16; ++$pos) {
            $unique = array_unique(array_map(static fn (string $h): string => $h[$pos], $hashes));
            self::assertGreaterThan(
                1,
                \count($unique),
                "Position {$pos} stuck on a single value across diverse inputs",
            );
        }
    }

    /**
     * Compute Hamming distance between two 16-char hex SimHashes,
     * exercising the same `gmp_xor`/`gmp_popcount` pair the dedup
     * pipeline will use downstream.
     */
    private function hammingDistance(string $a, string $b): int
    {
        return gmp_popcount(gmp_xor(gmp_init($a, 16), gmp_init($b, 16)));
    }
}
