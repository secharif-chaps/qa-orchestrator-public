<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Fingerprinting;

use App\Domain\Document\Deduplication\FingerprintSimilarity;
use App\Domain\Document\Fingerprinting\DocumentFingerprintComputer;
use App\Domain\Document\Fingerprinting\Fingerprint;
use App\Domain\Document\Fingerprinting\LshBandGenerator;
use App\Domain\Document\Fingerprinting\MinHashGenerator;
use App\Domain\Document\Fingerprinting\ScriptDetector;
use App\Domain\Document\Fingerprinting\ShingleExtractor;
use App\Domain\Document\Fingerprinting\SimHashGenerator;
use App\Domain\Document\Fingerprinting\TextNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end on the algorithm orchestrator: every layer is wired with the
 * real (deterministic) generator so we can assert on the produced
 * Fingerprint shape — not just that the right method got called.
 */
#[CoversClass(DocumentFingerprintComputer::class)]
class DocumentFingerprintComputerTest extends TestCase
{
    private DocumentFingerprintComputer $computer;

    protected function setUp(): void
    {
        $this->computer = new DocumentFingerprintComputer(
            new TextNormalizer(),
            new ShingleExtractor(new ScriptDetector()),
            new SimHashGenerator(),
            new MinHashGenerator(),
            new LshBandGenerator(),
        );
    }

    public function testComputesAFingerprintWithAllFourLayers(): void
    {
        $fingerprint = $this->computer->compute(
            'Le président français a annoncé une réforme majeure aujourd\'hui à Paris.',
        );

        self::assertInstanceOf(Fingerprint::class, $fingerprint);
        self::assertSame(64, \strlen($fingerprint->contentHash));            // sha256 hex
        self::assertSame(16, \strlen($fingerprint->simHash));                 // 64-bit hex
        self::assertCount(MinHashGenerator::NUM_HASHES, $fingerprint->minHashSignature);
        self::assertCount(LshBandGenerator::NUM_BANDS, $fingerprint->lshBands);
    }

    public function testIdenticalInputProducesIdenticalFingerprint(): void
    {
        $text = 'Stable input → bit-identical output across runs and instances.';

        $first = $this->computer->compute($text);
        $second = $this->computer->compute($text);

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame($first->contentHash, $second->contentHash);
        self::assertSame($first->simHash, $second->simHash);
        self::assertSame($first->minHashSignature, $second->minHashSignature);
        self::assertSame($first->lshBands, $second->lshBands);
    }

    public function testHashIsComputedOverNormalisedTextNotRawInput(): void
    {
        // Pin the contract: callers passing slightly-different bytes that
        // normalise to the same canonical form get the same contentHash.
        // This is what makes stage 1 (SHA256 lookup) tolerant to
        // encoding/whitespace noise without dropping precision.
        $raw = "<p>Bonjour <strong>monde</strong>!</p>\n\n";
        $cleaned = 'Bonjour monde !';

        $rawFingerprint = $this->computer->compute($raw);
        $cleanedFingerprint = $this->computer->compute($cleaned);

        self::assertNotNull($rawFingerprint);
        self::assertNotNull($cleanedFingerprint);
        self::assertSame($rawFingerprint->contentHash, $cleanedFingerprint->contentHash);
    }

    public function testNearDuplicateTextsProduceSimilarSimHash(): void
    {
        // Two long paragraphs differing by a single trailing sentence
        // should share most SimHash bits — far below the 32-bit Hamming
        // distance you'd see between unrelated documents (random ≈ 32).
        $base = <<<TEXT
            The Eiffel Tower is a wrought-iron lattice tower on the Champ de Mars in Paris, France.
            It is named after the engineer Gustave Eiffel, whose company designed and built the tower.
            Locally nicknamed "La dame de fer", it was constructed from 1887 to 1889 as the centrepiece
            of the 1889 World's Fair. It was initially criticised by some of France's leading artists
            and intellectuals for its design, but it has become a global cultural icon of France and
            one of the most recognisable structures in the world.
            TEXT;

        $original = $base;
        $nearDup = $base . ' Today it remains one of the most-visited paid monuments on Earth.';

        $a = $this->computer->compute($original);
        $b = $this->computer->compute($nearDup);

        self::assertNotNull($a);
        self::assertNotNull($b);
        $similarity = new FingerprintSimilarity();
        $hamming = $similarity->hamming($a, $b);
        self::assertLessThan(16, $hamming, "Expected SimHash to track text similarity; got Hamming={$hamming}");
    }

    public function testCompletelyDifferentTextsProduceDistinctFingerprints(): void
    {
        $a = $this->computer->compute('Apples and oranges grow on trees in orchards.');
        $b = $this->computer->compute('Quantum mechanics describes subatomic particles.');

        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertNotSame($a->contentHash, $b->contentHash);
        self::assertNotSame($a->simHash, $b->simHash);
    }

    public function testEmptyTextReturnsNullFingerprint(): void
    {
        // Empty input or chrome-only HTML normalises to no shingles. The
        // computer must return null rather than a degenerate
        // all-zero bundle — otherwise every content-free document would
        // collide on the same `sha256("")`/single-band sentinel keys at
        // stages 1-3 of the dedup pipeline.
        self::assertNull($this->computer->compute(''));
        self::assertNull($this->computer->compute('   '));
        // HTML chrome (link/img/script) without any real text body —
        // the same shape as the production document that triggered the
        // bug fix.
        self::assertNull(
            $this->computer->compute('<link rel="stylesheet" href="x"><img src="y"><script>noop()</script>'),
        );
    }

    public function testCjkTextProducesAValidFingerprint(): void
    {
        // CJK path is exercised through ScriptDetector + character-n-gram
        // extraction. We don't pin specific values (depends on hash funcs)
        // but require the bundle is well-formed.
        $fingerprint = $this->computer->compute(
            '東京都は日本の首都です。新宿区は東京都の中心地です。'
        );

        self::assertNotNull($fingerprint);
        self::assertSame(64, \strlen($fingerprint->contentHash));
        self::assertSame(16, \strlen($fingerprint->simHash));
        self::assertCount(MinHashGenerator::NUM_HASHES, $fingerprint->minHashSignature);
    }
}
