<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Deduplication;

use App\Domain\Document\Deduplication\FingerprintSimilarity;
use App\Domain\Document\Fingerprinting\Fingerprint;
use App\Domain\Document\Fingerprinting\LshBandGenerator;
use App\Domain\Document\Fingerprinting\MinHashGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FingerprintSimilarity::class)]
class FingerprintSimilarityTest extends TestCase
{
    private FingerprintSimilarity $similarity;

    protected function setUp(): void
    {
        $this->similarity = new FingerprintSimilarity();
    }

    public function testHammingOfIdenticalSimHashesIsZero(): void
    {
        $fp = $this->makeFingerprint(simHash: 'ffeeddccbbaa9988');

        self::assertSame(0, $this->similarity->hamming($fp, $fp));
    }

    public function testHammingCountsDifferingBits(): void
    {
        // 0x00...01 vs 0x00...00 → exactly 1 bit flipped.
        $a = $this->makeFingerprint(simHash: '0000000000000001');
        $b = $this->makeFingerprint(simHash: '0000000000000000');

        self::assertSame(1, $this->similarity->hamming($a, $b));
    }

    public function testHammingHandlesAllBitsFlipped(): void
    {
        $a = $this->makeFingerprint(simHash: 'ffffffffffffffff');
        $b = $this->makeFingerprint(simHash: '0000000000000000');

        self::assertSame(64, $this->similarity->hamming($a, $b));
    }

    public function testHammingIsSymmetric(): void
    {
        $a = $this->makeFingerprint(simHash: 'a1b2c3d4e5f60718');
        $b = $this->makeFingerprint(simHash: '0123456789abcdef');

        self::assertSame($this->similarity->hamming($a, $b), $this->similarity->hamming($b, $a));
    }

    public function testHammingToSimilarityIsOneAtZeroDistance(): void
    {
        self::assertSame(1.0, $this->similarity->hammingToSimilarity(0));
    }

    public function testHammingToSimilarityIsZeroAtFullDistance(): void
    {
        self::assertSame(0.0, $this->similarity->hammingToSimilarity(64));
    }

    public function testHammingToSimilarityScalesLinearly(): void
    {
        // 3 bits / 64 = ~0.953
        self::assertEqualsWithDelta(0.953, $this->similarity->hammingToSimilarity(3), 0.001);
    }

    public function testJaccardOfIdenticalSignaturesIsOne(): void
    {
        /** @var list<int> $signature */
        $signature = array_fill(0, MinHashGenerator::NUM_HASHES, 42);
        $fp = $this->makeFingerprint(minHashSignature: $signature);

        self::assertSame(1.0, $this->similarity->jaccard($fp, $fp));
    }

    public function testJaccardOfFullyDisjointSignaturesIsZero(): void
    {
        /** @var list<int> $sigA */
        $sigA = array_fill(0, MinHashGenerator::NUM_HASHES, 1);
        /** @var list<int> $sigB */
        $sigB = array_fill(0, MinHashGenerator::NUM_HASHES, 2);

        $a = $this->makeFingerprint(minHashSignature: $sigA);
        $b = $this->makeFingerprint(minHashSignature: $sigB);

        self::assertSame(0.0, $this->similarity->jaccard($a, $b));
    }

    public function testJaccardCountsCollidingSlots(): void
    {
        // 64 / 128 collisions → 0.5 Jaccard estimate.
        $sigA = array_map(static fn (): int => 0, range(0, MinHashGenerator::NUM_HASHES - 1));
        $sigB = array_map(static fn (int $i): int => $i < 64 ? 0 : 1, range(0, MinHashGenerator::NUM_HASHES - 1));

        $a = $this->makeFingerprint(minHashSignature: $sigA);
        $b = $this->makeFingerprint(minHashSignature: $sigB);

        self::assertSame(0.5, $this->similarity->jaccard($a, $b));
    }

    public function testJaccardIsSymmetric(): void
    {
        $sigA = array_map(static fn (int $i): int => $i * 7, range(0, MinHashGenerator::NUM_HASHES - 1));
        $sigB = array_map(static fn (int $i): int => $i * 11, range(0, MinHashGenerator::NUM_HASHES - 1));

        $a = $this->makeFingerprint(minHashSignature: $sigA);
        $b = $this->makeFingerprint(minHashSignature: $sigB);

        self::assertSame($this->similarity->jaccard($a, $b), $this->similarity->jaccard($b, $a));
    }

    /**
     * @param list<int>|null    $minHashSignature
     * @param list<string>|null $lshBands
     */
    private function makeFingerprint(
        string $contentHash = '',
        string $simHash = '0000000000000000',
        ?array $minHashSignature = null,
        ?array $lshBands = null,
    ): Fingerprint {
        /** @var list<int> $defaultSignature */
        $defaultSignature = array_fill(0, MinHashGenerator::NUM_HASHES, 0);
        /** @var list<string> $defaultBands */
        $defaultBands = array_fill(0, LshBandGenerator::NUM_BANDS, str_repeat('0', 32));

        return new Fingerprint(
            contentHash: $contentHash,
            simHash: $simHash,
            minHashSignature: $minHashSignature ?? $defaultSignature,
            lshBands: $lshBands ?? $defaultBands,
        );
    }
}
