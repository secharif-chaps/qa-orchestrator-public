<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Fingerprinting;

use App\Domain\Document\Fingerprinting\Fingerprint;
use App\Domain\Document\Fingerprinting\LshBandGenerator;
use App\Domain\Document\Fingerprinting\MinHashGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Fingerprint::class)]
class FingerprintTest extends TestCase
{
    public function testValidConstruction(): void
    {
        $signature = array_fill(0, MinHashGenerator::NUM_HASHES, 0);
        $bands = array_fill(0, LshBandGenerator::NUM_BANDS, str_repeat('0', 32));

        $fingerprint = new Fingerprint(
            contentHash: str_repeat('a', 64),
            simHash: str_repeat('0', 16),
            minHashSignature: $signature,
            lshBands: $bands,
        );

        self::assertSame(str_repeat('a', 64), $fingerprint->contentHash);
        self::assertSame(str_repeat('0', 16), $fingerprint->simHash);
        self::assertCount(128, $fingerprint->minHashSignature);
        self::assertCount(32, $fingerprint->lshBands);
    }

    public function testRejectsMinHashSignatureOfWrongSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly 128 values, got 64');

        new Fingerprint(
            contentHash: str_repeat('a', 64),
            simHash: str_repeat('0', 16),
            minHashSignature: array_fill(0, 64, 0),
            lshBands: array_fill(0, LshBandGenerator::NUM_BANDS, str_repeat('0', 32)),
        );
    }

    public function testRejectsEmptyMinHashSignature(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Fingerprint(
            contentHash: str_repeat('a', 64),
            simHash: str_repeat('0', 16),
            minHashSignature: [],
            lshBands: array_fill(0, LshBandGenerator::NUM_BANDS, str_repeat('0', 32)),
        );
    }

    public function testRejectsLshBandsOfWrongSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly 32 band hashes, got 16');

        new Fingerprint(
            contentHash: str_repeat('a', 64),
            simHash: str_repeat('0', 16),
            minHashSignature: array_fill(0, MinHashGenerator::NUM_HASHES, 0),
            lshBands: array_fill(0, 16, str_repeat('0', 32)),
        );
    }

    public function testRejectsEmptyLshBands(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Fingerprint(
            contentHash: str_repeat('a', 64),
            simHash: str_repeat('0', 16),
            minHashSignature: array_fill(0, MinHashGenerator::NUM_HASHES, 0),
            lshBands: [],
        );
    }

    public function testIsImmutable(): void
    {
        $signature = array_fill(0, MinHashGenerator::NUM_HASHES, 0);
        $bands = array_fill(0, LshBandGenerator::NUM_BANDS, str_repeat('0', 32));

        $fingerprint = new Fingerprint(
            contentHash: 'abc',
            simHash: 'def',
            minHashSignature: $signature,
            lshBands: $bands,
        );

        // readonly class — direct write must fail.
        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line property.readOnlyByPhpDocAssignNotInConstructor
        $fingerprint->contentHash = 'mutated';
    }
}
