<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Fingerprinting;

use App\Domain\Document\Fingerprinting\LshBandGenerator;
use App\Domain\Document\Fingerprinting\MinHashGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LshBandGenerator::class)]
class LshBandGeneratorTest extends TestCase
{
    private LshBandGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new LshBandGenerator();
    }

    // ─── Output format ───────────────────────────────────────────────────

    public function testProducesExactly32BandHashes(): void
    {
        $signature = $this->fixedSignature();

        $bands = $this->generator->generate($signature);

        self::assertCount(LshBandGenerator::NUM_BANDS, $bands);
        self::assertCount(32, $bands);
    }

    public function testEachBandHashIs32CharLowercaseHex(): void
    {
        $bands = $this->generator->generate($this->fixedSignature());

        foreach ($bands as $hash) {
            self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $hash);
        }
    }

    public function testBandConfigurationMatchesMinHashSize(): void
    {
        // Sanity: bands × rows must equal the MinHash size.
        self::assertSame(
            MinHashGenerator::NUM_HASHES,
            LshBandGenerator::NUM_BANDS * LshBandGenerator::ROWS_PER_BAND,
        );
    }

    // ─── Validation ──────────────────────────────────────────────────────

    public function testRejectsSignatureOfWrongSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly 128');

        $this->generator->generate(array_fill(0, 64, 0));
    }

    public function testRejectsEmptySignature(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->generator->generate([]);
    }

    public function testRejectsOversizedSignature(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->generator->generate(array_fill(0, 256, 0));
    }

    // ─── Determinism / reproducibility ───────────────────────────────────

    public function testIsDeterministicForSameSignature(): void
    {
        $signature = $this->fixedSignature();

        self::assertSame($this->generator->generate($signature), $this->generator->generate($signature));
    }

    public function testTwoInstancesProduceSameBands(): void
    {
        $signature = $this->fixedSignature();

        self::assertSame(
            new LshBandGenerator()
->generate($signature),
            new LshBandGenerator()
->generate($signature),
        );
    }

    // ─── Sensitivity ─────────────────────────────────────────────────────

    public function testFlippingASingleSlotChangesExactlyOneBand(): void
    {
        // Each band covers ROWS_PER_BAND consecutive slots, so a single
        // edit affects exactly one band — anchors the layout against
        // accidental row-stride regressions.
        $signature = $this->fixedSignature();
        $modified = $signature;
        $modified[5] = 999_999_999; // index 5 falls in band 1 (rows 4-7)

        $original = $this->generator->generate($signature);
        $changed = $this->generator->generate(array_values($modified));

        $diffCount = 0;
        foreach ($original as $i => $hash) {
            if ($hash !== $changed[$i]) {
                ++$diffCount;
            }
        }

        self::assertSame(1, $diffCount, 'A single-slot edit must flip exactly one band hash');
    }

    public function testFlippingSlotInBoundaryAffectsCorrectBand(): void
    {
        // Index 0 → band 0; index 4 → band 1; index 124 → band 31.
        $signature = $this->fixedSignature();

        foreach ([
            0 => 0,
            4 => 1,
            124 => 31,
        ] as $slot => $expectedBand) {
            $modified = $signature;
            $modified[$slot] = 12_345;

            $base = $this->generator->generate($signature);
            $changed = $this->generator->generate(array_values($modified));

            for ($band = 0; $band < LshBandGenerator::NUM_BANDS; ++$band) {
                if ($band === $expectedBand) {
                    self::assertNotSame(
                        $base[$band],
                        $changed[$band],
                        "Slot {$slot} should have moved band {$expectedBand}",
                    );
                } else {
                    self::assertSame(
                        $base[$band],
                        $changed[$band],
                        "Slot {$slot} unexpectedly moved band {$band}",
                    );
                }
            }
        }
    }

    public function testCompletelyDifferentSignaturesShareNoBand(): void
    {
        $a = array_fill(0, 128, 1);
        $b = array_fill(0, 128, 2);

        $bandsA = $this->generator->generate($a);
        $bandsB = $this->generator->generate($b);

        // No band should match at all → no false LSH candidates.
        self::assertEmpty(array_intersect($bandsA, $bandsB));
    }

    public function testSimilarSignaturesShareSomeBands(): void
    {
        // Identical first 64 slots → first 16 bands should match.
        $a = array_fill(0, 128, 0);
        $b = array_fill(0, 128, 0);
        for ($i = 0; $i < 64; ++$i) {
            $a[$i] = $i;
            $b[$i] = $i;
        }
        for ($i = 64; $i < 128; ++$i) {
            $a[$i] = $i;
            $b[$i] = $i + 1_000_000;
        }

        $bandsA = $this->generator->generate(array_values($a));
        $bandsB = $this->generator->generate(array_values($b));

        $shared = 0;
        for ($band = 0; $band < LshBandGenerator::NUM_BANDS; ++$band) {
            if ($bandsA[$band] === $bandsB[$band]) {
                ++$shared;
            }
        }

        self::assertSame(16, $shared, 'First 16 bands must match when first 64 slots are identical');
    }

    /**
     * @return list<int>
     */
    private function fixedSignature(): array
    {
        // Deterministic synthetic signature — each slot = its index, so
        // assertions about band boundaries stay readable.
        return range(0, MinHashGenerator::NUM_HASHES - 1);
    }
}
