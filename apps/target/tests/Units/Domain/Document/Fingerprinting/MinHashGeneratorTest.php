<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Fingerprinting;

use App\Domain\Document\Fingerprinting\MinHashGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MinHashGenerator::class)]
class MinHashGeneratorTest extends TestCase
{
    private MinHashGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new MinHashGenerator();
    }

    // ─── Output shape ────────────────────────────────────────────────────

    public function testEmptyShinglesProducesAllZeroSignature(): void
    {
        $signature = $this->generator->generate([]);

        self::assertCount(MinHashGenerator::NUM_HASHES, $signature);
        self::assertSame(array_fill(0, MinHashGenerator::NUM_HASHES, 0), $signature);
    }

    public function testSignatureIsAlwaysExactly128Values(): void
    {
        $signature = $this->generator->generate(['hello world']);

        self::assertCount(128, $signature);
        self::assertSame(128, MinHashGenerator::NUM_HASHES);
    }

    public function testValuesAreUnsigned32BitInRange(): void
    {
        $signature = $this->generator->generate(['the quick brown fox', 'jumps over', 'the lazy dog']);

        foreach ($signature as $value) {
            self::assertGreaterThanOrEqual(0, $value);
            // Mersenne prime upper bound — values are in [0, PRIME).
            self::assertLessThan(2_147_483_647, $value);
        }
    }

    // ─── Determinism / reproducibility ───────────────────────────────────

    public function testIsDeterministicForSameInput(): void
    {
        $shingles = ['alpha beta', 'beta gamma', 'gamma delta'];

        self::assertSame($this->generator->generate($shingles), $this->generator->generate($shingles));
    }

    public function testIsOrderIndependent(): void
    {
        $forward = $this->generator->generate(['a', 'b', 'c']);
        $reversed = $this->generator->generate(['c', 'b', 'a']);

        self::assertSame($forward, $reversed);
    }

    public function testReproducibleAcrossInstances(): void
    {
        // Same seed (42) → two independently-constructed generators must
        // emit the same signature byte-for-byte. This is what makes
        // signatures persistable to OpenSearch and queryable across
        // service restarts.
        $a = new MinHashGenerator()
->generate(['hello world']);
        $b = new MinHashGenerator()
->generate(['hello world']);

        self::assertSame($a, $b);
    }

    public function testDoesNotDisturbGlobalMtRandState(): void
    {
        // The ADR sketch used `mt_srand(42)` + `mt_srand()` at construct
        // time, polluting the global mt_rand stream. We use a private
        // `\Random\Randomizer` instead — assert we left the global PRNG
        // alone.
        mt_srand(123);
        $expected = mt_rand();

        mt_srand(123);
        new MinHashGenerator();
        $actual = mt_rand();

        self::assertSame($expected, $actual, 'MinHashGenerator polluted global mt_rand state');
    }

    // ─── Sensitivity / Jaccard estimation ────────────────────────────────

    public function testIdenticalShingleSetsHaveAllSlotsEqual(): void
    {
        $shingles = ['cat', 'dog', 'bird', 'fish'];
        $a = $this->generator->generate($shingles);
        $b = $this->generator->generate($shingles);

        self::assertSame($a, $b);
        self::assertSame(MinHashGenerator::NUM_HASHES, $this->countMatchingSlots($a, $b));
    }

    public function testDisjointShingleSetsHaveFewMatchingSlots(): void
    {
        $a = $this->generator->generate(['cat', 'dog', 'bird', 'fish', 'mouse']);
        $b = $this->generator->generate(['plane', 'train', 'car', 'boat', 'truck']);

        $matches = $this->countMatchingSlots($a, $b);

        // Disjoint sets → expected Jaccard 0 → expected matches near 0.
        // Allow some slack for hash collisions on small sets.
        self::assertLessThan(20, $matches, "Got {$matches} matches on disjoint sets, expected near 0");
    }

    public function testJaccardEstimateApproximatesTrueJaccardOnLargeSets(): void
    {
        // Build two sets with a controlled overlap. With 200 shingles
        // each and 100 in common, true Jaccard = 100 / 300 ≈ 0.333.
        // 128-hash MinHash standard error ≈ 1/√128 ≈ 0.088, so we
        // accept ±0.20 absolute (defensive — generous for CI noise).
        $common = [];
        $onlyA = [];
        $onlyB = [];
        for ($i = 0; $i < 100; ++$i) {
            $common[] = "common-{$i}";
        }
        for ($i = 0; $i < 100; ++$i) {
            $onlyA[] = "only-a-{$i}";
            $onlyB[] = "only-b-{$i}";
        }

        $sigA = $this->generator->generate(array_merge($common, $onlyA));
        $sigB = $this->generator->generate(array_merge($common, $onlyB));

        $estimate = $this->countMatchingSlots($sigA, $sigB) / MinHashGenerator::NUM_HASHES;
        $expected = 100 / 300;

        self::assertEqualsWithDelta(
            $expected,
            $estimate,
            0.20,
            "Jaccard estimate {$estimate} too far from expected {$expected}",
        );
    }

    public function testSingleShingleProducesValidSignature(): void
    {
        $signature = $this->generator->generate(['lonely']);

        self::assertCount(128, $signature);
        // At least one slot should be < PHP_INT_MAX (i.e. updated from
        // its initial sentinel).
        self::assertNotSame(array_fill(0, 128, \PHP_INT_MAX), $signature);
    }

    // ─── Robustness ──────────────────────────────────────────────────────

    public function testHandlesUnicodeShingles(): void
    {
        $signature = $this->generator->generate(['東京都は日本', '京都は日本の', '都は日本の首']);

        self::assertCount(128, $signature);
    }

    public function testHandlesGenerator(): void
    {
        $shingles = (function (): \Generator {
            yield 'a';
            yield 'b';
            yield 'c';
        })();

        $signature = $this->generator->generate($shingles);

        self::assertCount(128, $signature);
    }

    public function testHandlesModerateShingleSetWithinTimeBudget(): void
    {
        $shingles = [];
        for ($i = 0; $i < 1_000; ++$i) {
            $shingles[] = "shingle-{$i}";
        }

        $start = microtime(true);
        $this->generator->generate($shingles);
        $elapsed = microtime(true) - $start;

        // 1k shingles × 128 GMP ops = 128k ops. Generous bound.
        self::assertLessThan(5.0, $elapsed, "Took {$elapsed}s on 1k shingles");
    }

    /**
     * @param list<int> $a
     * @param list<int> $b
     */
    private function countMatchingSlots(array $a, array $b): int
    {
        $matches = 0;
        foreach ($a as $i => $value) {
            if ($value === $b[$i]) {
                ++$matches;
            }
        }

        return $matches;
    }
}
