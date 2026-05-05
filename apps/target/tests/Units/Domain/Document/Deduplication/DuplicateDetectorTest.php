<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Deduplication;

use App\Domain\Document\Deduplication\DuplicateDetector;
use App\Domain\Document\Deduplication\DuplicateMatchStage;
use App\Domain\Document\Deduplication\DuplicateOutcome;
use App\Domain\Document\Deduplication\FingerprintGatewayInterface;
use App\Domain\Document\Deduplication\FingerprintSimilarity;
use App\Domain\Document\Document;
use App\Domain\Document\Fingerprinting\Fingerprint;
use App\Domain\Document\Fingerprinting\LshBandGenerator;
use App\Domain\Document\Fingerprinting\MinHashGenerator;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use App\Tests\Units\Infrastructure\Document\NullFingerprintGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Cover the five-stage cascade with hand-rolled gateways (canonical_url,
 * content_hash, sim_hash, min_hash_lsh, title_fallback). Each test pins
 * one stage and asserts both the verdict and that no later stage was
 * exercised (short-circuit) by relying on the fact that the in-memory
 * gateways' candidate sets are scoped to the documents the test loads.
 */
#[CoversClass(DuplicateDetector::class)]
class DuplicateDetectorTest extends TestCase
{
    private NullDocumentGateway $documentGateway;
    private NullFingerprintGateway $fingerprintGateway;
    private DuplicateDetector $detector;

    protected function setUp(): void
    {
        $this->documentGateway = new NullDocumentGateway();
        $this->fingerprintGateway = new NullFingerprintGateway();
        $this->detector = new DuplicateDetector(
            $this->documentGateway,
            $this->fingerprintGateway,
            new FingerprintSimilarity(),
        );
    }

    public function testNoMatchAnywhereReturnsUnique(): void
    {
        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(simHash: '0000000000000001'),
            canonicalUrl: 'https://example.com/article-1',
        );

        self::assertSame(DuplicateOutcome::UNIQUE, $result->outcome);
        self::assertNull($result->stage);
        self::assertFalse($result->isMatch());
    }

    public function testStage0CanonicalUrlMatchReturnsDuplicate(): void
    {
        $original = $this->indexDocument(
            id: 'doc-original',
            canonicalUrl: 'https://example.com/article',
            fingerprint: $this->makeFingerprint(simHash: 'aaaaaaaaaaaaaaaa'),
        );

        // Candidate has a *different* fingerprint — stage 0 must catch
        // it before stages 1-3 ever run.
        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(simHash: 'bbbbbbbbbbbbbbbb'),
            canonicalUrl: 'https://example.com/article',
        );

        self::assertSame(DuplicateOutcome::DUPLICATE, $result->outcome);
        self::assertSame(DuplicateMatchStage::CANONICAL_URL, $result->stage);
        self::assertSame($original->getId(), $result->originalDocumentId);
        self::assertSame(1.0, $result->similarity);
    }

    public function testStage1ContentHashMatchReturnsDuplicate(): void
    {
        $original = $this->indexDocument(
            id: 'doc-original',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(contentHash: 'sha256-abc'),
        );

        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(contentHash: 'sha256-abc'),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::DUPLICATE, $result->outcome);
        self::assertSame(DuplicateMatchStage::CONTENT_HASH, $result->stage);
        self::assertSame($original->getId(), $result->originalDocumentId);
        self::assertSame(1.0, $result->similarity);
    }

    public function testStage2SimHashExactMatchReturnsNearExact(): void
    {
        $original = $this->indexDocument(
            id: 'doc-original',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(contentHash: 'sha256-original', simHash: '1234567890abcdef'),
        );

        // Different content hash, identical SimHash → stage 2 catches it.
        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(contentHash: 'sha256-different', simHash: '1234567890abcdef'),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::NEAR_EXACT, $result->outcome);
        self::assertSame(DuplicateMatchStage::SIM_HASH, $result->stage);
        self::assertSame($original->getId(), $result->originalDocumentId);
        self::assertSame(1.0, $result->similarity);
    }

    public function testStage3HammingBelowThresholdReturnsNearExact(): void
    {
        // SimHash differs by 2 bits → Hamming = 2 (≤3) → NEAR_EXACT.
        // Bands shared so the candidate is reachable via LSH.
        $sharedBand = str_repeat('a', 32);
        $bands = $this->makeBands($sharedBand, 0);

        $original = $this->indexDocument(
            id: 'doc-original',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-original',
                simHash: '0000000000000000',
                lshBands: $bands,
            ),
        );

        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-candidate',
                simHash: '0000000000000003', // 2 bits flipped
                lshBands: $bands,
            ),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::NEAR_EXACT, $result->outcome);
        self::assertSame(DuplicateMatchStage::MIN_HASH_LSH, $result->stage);
        self::assertSame($original->getId(), $result->originalDocumentId);
        self::assertEqualsWithDelta(1.0 - 2 / 64, $result->similarity, 0.001);
    }

    public function testStage3JaccardAboveThresholdReturnsNearDuplicate(): void
    {
        // SimHash too different (Hamming > 3) but MinHash highly similar
        // (Jaccard ≥ 0.70 — TAR-1146 lowered the threshold from the
        // industry-standard 0.85 to capture syndicated press releases
        // with light edits) → NEAR_DUPLICATE.
        $sharedBand = str_repeat('b', 32);
        $bands = $this->makeBands($sharedBand, 0);

        // 110 / 128 colliding slots → Jaccard ≈ 0.86 (well above 0.70).
        $sigOriginal = array_map(static fn (): int => 1, range(0, MinHashGenerator::NUM_HASHES - 1));
        $sigCandidate = array_map(
            static fn (int $i): int => $i < 18 ? 999_999 + $i : 1,
            range(0, MinHashGenerator::NUM_HASHES - 1),
        );

        $original = $this->indexDocument(
            id: 'doc-original',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-original',
                simHash: '0000000000000000',
                minHashSignature: $sigOriginal,
                lshBands: $bands,
            ),
        );

        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-candidate',
                simHash: 'ffffffffffffffff', // Hamming 64 (well above 3)
                minHashSignature: $sigCandidate,
                lshBands: $bands,
            ),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::NEAR_DUPLICATE, $result->outcome);
        self::assertSame(DuplicateMatchStage::MIN_HASH_LSH, $result->stage);
        self::assertSame($original->getId(), $result->originalDocumentId);
        self::assertEqualsWithDelta(110 / 128, $result->similarity, 0.001);
    }

    public function testStage3JaccardJustAboveThresholdReturnsNearDuplicate(): void
    {
        // Boundary test for the 0.70 threshold introduced by TAR-1146:
        // 90 / 128 colliding slots → Jaccard ≈ 0.7031, just above 0.70.
        // A regression that bumped the threshold back to 0.85 would flip
        // this verdict to UNIQUE — which is exactly the behaviour the
        // tuning was meant to fix.
        $sharedBand = str_repeat('e', 32);
        $bands = $this->makeBands($sharedBand, 0);

        $sigOriginal = array_map(static fn (): int => 1, range(0, MinHashGenerator::NUM_HASHES - 1));
        $sigCandidate = array_map(
            static fn (int $i): int => $i < 38 ? 777_777 + $i : 1,
            range(0, MinHashGenerator::NUM_HASHES - 1),
        );

        $original = $this->indexDocument(
            id: 'doc-borderline-above',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-original',
                simHash: '0000000000000000',
                minHashSignature: $sigOriginal,
                lshBands: $bands,
            ),
        );

        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-candidate',
                simHash: 'ffffffffffffffff', // Hamming 64 (forces Jaccard path)
                minHashSignature: $sigCandidate,
                lshBands: $bands,
            ),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::NEAR_DUPLICATE, $result->outcome);
        self::assertSame(DuplicateMatchStage::MIN_HASH_LSH, $result->stage);
        self::assertSame($original->getId(), $result->originalDocumentId);
        self::assertGreaterThanOrEqual(0.70, $result->similarity);
        self::assertEqualsWithDelta(90 / 128, $result->similarity, 0.001);
    }

    public function testStage3JaccardJustBelowThresholdReturnsUnique(): void
    {
        // Counterpart of the previous test: 89 / 128 colliding slots →
        // Jaccard ≈ 0.6953, just below 0.70 → UNIQUE. The candidate is
        // still reachable via shared LSH bands so the gateway returns
        // it; the threshold check is what filters it out.
        $sharedBand = str_repeat('f', 32);
        $bands = $this->makeBands($sharedBand, 0);

        $sigOriginal = array_map(static fn (): int => 1, range(0, MinHashGenerator::NUM_HASHES - 1));
        $sigCandidate = array_map(
            static fn (int $i): int => $i < 39 ? 888_888 + $i : 1,
            range(0, MinHashGenerator::NUM_HASHES - 1),
        );

        $this->indexDocument(
            id: 'doc-borderline-below',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-original',
                simHash: '0000000000000000',
                minHashSignature: $sigOriginal,
                lshBands: $bands,
            ),
        );

        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-candidate',
                simHash: 'ffffffffffffffff',
                minHashSignature: $sigCandidate,
                lshBands: $bands,
            ),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::UNIQUE, $result->outcome);
        self::assertNull($result->stage);
    }

    public function testStage3HammingExactlyAtThresholdReturnsNearExact(): void
    {
        // Boundary test for the Hamming ≤ 3 threshold: Hamming = 3
        // (last value that still classifies as NEAR_EXACT). Hamming = 4
        // is exercised by `testStage3HammingJustAboveThresholdFallsThroughToJaccard`.
        $sharedBand = str_repeat('g', 32);
        $bands = $this->makeBands($sharedBand, 0);

        $original = $this->indexDocument(
            id: 'doc-hamming-3',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-original',
                simHash: '0000000000000000',
                lshBands: $bands,
            ),
        );

        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-candidate',
                simHash: '0000000000000007', // 3 bits flipped
                lshBands: $bands,
            ),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::NEAR_EXACT, $result->outcome);
        self::assertSame(DuplicateMatchStage::MIN_HASH_LSH, $result->stage);
        self::assertSame($original->getId(), $result->originalDocumentId);
        self::assertEqualsWithDelta(1.0 - 3 / 64, $result->similarity, 0.001);
    }

    public function testStage3HammingJustAboveThresholdFallsThroughToJaccard(): void
    {
        // Hamming = 4 (just above the ≤ 3 NEAR_EXACT threshold) → must
        // fall through to the Jaccard branch. With identical MinHash
        // signatures the Jaccard would be 1.0 → NEAR_DUPLICATE — but
        // that would be ambiguous with stage 2/3 NEAR_EXACT. Use a
        // signature that produces Jaccard < 0.70 so the verdict cleanly
        // switches to UNIQUE.
        $sharedBand = str_repeat('h', 32);
        $bands = $this->makeBands($sharedBand, 0);

        $sigOriginal = array_map(static fn (): int => 1, range(0, MinHashGenerator::NUM_HASHES - 1));
        // 60 / 128 collisions → Jaccard ≈ 0.469, well below 0.70.
        $sigCandidate = array_map(
            static fn (int $i): int => $i < 68 ? 555_555 + $i : 1,
            range(0, MinHashGenerator::NUM_HASHES - 1),
        );

        $this->indexDocument(
            id: 'doc-fallthrough',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-original',
                simHash: '0000000000000000',
                minHashSignature: $sigOriginal,
                lshBands: $bands,
            ),
        );

        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-candidate',
                simHash: '000000000000000f', // 4 bits flipped → Hamming 4
                minHashSignature: $sigCandidate,
                lshBands: $bands,
            ),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::UNIQUE, $result->outcome);
        self::assertNull($result->stage);
    }

    public function testStage3PicksTheHighestSimilarityCandidate(): void
    {
        $sharedBand = str_repeat('c', 32);
        $bands = $this->makeBands($sharedBand, 0);

        // Candidate A: Hamming 3 → similarity 0.953
        $candidateA = $this->indexDocument(
            id: 'doc-far',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-far',
                simHash: '0000000000000007',
                lshBands: $bands,
            ),
        );

        // Candidate B: Hamming 1 → similarity 0.984 — should win.
        $candidateB = $this->indexDocument(
            id: 'doc-near',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-near',
                simHash: '0000000000000001',
                lshBands: $bands,
            ),
        );

        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-probe',
                simHash: '0000000000000000',
                lshBands: $bands,
            ),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::NEAR_EXACT, $result->outcome);
        self::assertSame($candidateB->getId(), $result->originalDocumentId);
        self::assertNotSame($candidateA->getId(), $result->originalDocumentId);
    }

    public function testExcludeDocumentIdSkipsSelfMatchAcrossAllStages(): void
    {
        // Re-index of the same document: every stage would match the
        // existing copy. With the exclusion in place, the verdict must
        // be UNIQUE.
        $self = $this->indexDocument(
            id: 'doc-self',
            canonicalUrl: 'https://example.com/article',
            fingerprint: $this->makeFingerprint(contentHash: 'sha256-self', simHash: '1234567890abcdef'),
        );

        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(contentHash: 'sha256-self', simHash: '1234567890abcdef'),
            canonicalUrl: 'https://example.com/article',
            excludeDocumentId: $self->getId(),
        );

        self::assertSame(DuplicateOutcome::UNIQUE, $result->outcome);
    }

    public function testStage0ShortCircuitsBeforeFingerprintLookup(): void
    {
        // Stage 0 catches it; there's a different document on stage 1
        // that would also match — must not be reached, so the verdict
        // should reference the canonical-URL match (stage 0), never the
        // contentHash one (stage 1).
        $stage0 = $this->indexDocument(
            id: 'doc-canonical',
            canonicalUrl: 'https://example.com/article',
            fingerprint: $this->makeFingerprint(contentHash: 'sha256-X', simHash: 'aaaaaaaaaaaaaaaa'),
        );
        $this->indexDocument(
            id: 'doc-content-hash',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(contentHash: 'sha256-Y', simHash: 'bbbbbbbbbbbbbbbb'),
        );

        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(contentHash: 'sha256-Y', simHash: 'aaaaaaaaaaaaaaaa'),
            canonicalUrl: 'https://example.com/article',
        );

        self::assertSame(DuplicateMatchStage::CANONICAL_URL, $result->stage);
        self::assertSame($stage0->getId(), $result->originalDocumentId);
    }

    public function testCandidatesWithoutFingerprintAreSilentlySkipped(): void
    {
        // Defensive skip path inside `DuplicateDetector::matchByLshCandidates`:
        // a candidate returned by the gateway with `getFingerprint() === null`
        // (e.g. a doc indexed pre-fingerprinting whose lshBands field is
        // somehow populated but whose nested fingerprint payload is not)
        // must be skipped without crashing.
        //
        // The default `NullFingerprintGateway` filters such candidates out
        // before they reach the detector (mirrors the OpenSearch term
        // query). To actually exercise the detector's defensive skip we
        // need a gateway that *does* return a no-fingerprint candidate —
        // this is what the inline stub below provides.
        $documentWithoutFingerprint = new Document(
            id: 'doc-legacy',
            title: 't',
            excerpt: 'excerpt-long-enough',
            type: 'article',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'c',
        );

        $unfingerprintedGateway = new class($documentWithoutFingerprint) implements FingerprintGatewayInterface {
            public function __construct(
                private readonly Document $unfingerprintedCandidate,
            ) {
            }

            public function findByContentHash(string $contentHash, ?string $excludeDocumentId = null): ?Document
            {
                return null;
            }

            public function findBySimHash(string $simHash, ?string $excludeDocumentId = null): array
            {
                return [];
            }

            public function findByLshBands(array $bandHashes, ?string $excludeDocumentId = null, int $limit = 50): array
            {
                // Always return the no-fingerprint candidate so the detector
                // hits its `null === $candidateFingerprint` branch.
                return [$this->unfingerprintedCandidate];
            }

            public function findByTitleShingles(
                array $titleShingles,
                ?string $excludeDocumentId = null,
                int $limit = 50,
            ): array {
                return [];
            }
        };

        $detector = new DuplicateDetector(
            $this->documentGateway,
            $unfingerprintedGateway,
            new FingerprintSimilarity(),
        );

        $result = $detector->detect(
            fingerprint: $this->makeFingerprint(simHash: '0000000000000000'),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::UNIQUE, $result->outcome);
        self::assertNull($result->stage);
    }

    public function testStage4TitleFallbackMatchesAboveThresholdReturnsNearDuplicate(): void
    {
        // Stage 4 fires only when stages 0-3 have all returned UNIQUE.
        // Use unique LSH bands per doc so stage 3 finds no candidate;
        // overlap the title shingles heavily so stage 4 catches it —
        // wire-syndication pattern: same headline reposted by multiple
        // outlets with rewritten bodies.
        $shared = ['vestas', 'estas-receives', 'receives-an', 'an-order', 'order-for', 'for-65', '65-mw'];

        $original = $this->indexDocument(
            id: 'doc-syndicated',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-original',
                simHash: '0000000000000000',
                lshBands: $this->uniqueBands('original'),
                titleShingles: $shared,
            ),
        );

        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-candidate',
                simHash: 'ffffffffffffffff',
                lshBands: $this->uniqueBands('candidate'),
                titleShingles: $shared,
            ),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::NEAR_DUPLICATE, $result->outcome);
        self::assertSame(DuplicateMatchStage::TITLE_FALLBACK, $result->stage);
        self::assertSame($original->getId(), $result->originalDocumentId);
        self::assertSame(1.0, $result->similarity);
    }

    public function testStage4TitleFallbackBelowThresholdReturnsUnique(): void
    {
        // Title shingles overlap exists but the set-Jaccard is < 0.85
        // (3 shared shingles out of 7 unique → Jaccard ≈ 0.43 ≪ 0.85).
        // The gateway returns the candidate (it shares ≥ 1 shingle) but
        // the detector's threshold check filters it out → UNIQUE.
        // Unique LSH bands keep stage 3 out of the cascade.
        $originalShingles = ['lorem', 'ipsum', 'dolor', 'sit', 'amet'];
        $candidateShingles = ['lorem', 'ipsum', 'dolor', 'consectetur', 'adipiscing'];

        $this->indexDocument(
            id: 'doc-low-title-overlap',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-original',
                simHash: '0000000000000000',
                lshBands: $this->uniqueBands('low-overlap-original'),
                titleShingles: $originalShingles,
            ),
        );

        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-candidate',
                simHash: 'ffffffffffffffff',
                lshBands: $this->uniqueBands('low-overlap-candidate'),
                titleShingles: $candidateShingles,
            ),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::UNIQUE, $result->outcome);
        self::assertNull($result->stage);
    }

    public function testStage4TitleFallbackSkippedWhenFingerprintHasNoTitleShingles(): void
    {
        // A document indexed before stage 4 shipped (or one whose title
        // produced no shingles, e.g. an empty / single-word title) carries
        // an empty `titleShingles` list. The detector must short-circuit
        // before any gateway call, otherwise a `findByTitleShingles([])`
        // would either throw or scan the whole index.
        $original = $this->indexDocument(
            id: 'doc-with-title',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-original',
                simHash: '0000000000000000',
                titleShingles: ['some', 'title', 'shingles'],
            ),
        );

        $countingGateway = new class($original) implements FingerprintGatewayInterface {
            public int $titleLookupCount = 0;

            public function __construct(
                private readonly Document $candidate,
            ) {
            }

            public function findByContentHash(string $contentHash, ?string $excludeDocumentId = null): ?Document
            {
                return null;
            }

            public function findBySimHash(string $simHash, ?string $excludeDocumentId = null): array
            {
                return [];
            }

            public function findByLshBands(array $bandHashes, ?string $excludeDocumentId = null, int $limit = 50): array
            {
                return [];
            }

            public function findByTitleShingles(
                array $titleShingles,
                ?string $excludeDocumentId = null,
                int $limit = 50,
            ): array {
                ++$this->titleLookupCount;

                return [$this->candidate];
            }
        };

        $detector = new DuplicateDetector($this->documentGateway, $countingGateway, new FingerprintSimilarity());

        $result = $detector->detect(
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-candidate',
                simHash: 'ffffffffffffffff',
                titleShingles: [],
            ),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::UNIQUE, $result->outcome);
        self::assertSame(
            0,
            $countingGateway->titleLookupCount,
            'Stage 4 must not touch the gateway when the candidate has no title shingles'
        );
    }

    public function testStage4TitleFallbackPicksHighestSimilarityCandidate(): void
    {
        // Two stored docs both reachable via title shingles. The detector
        // must pick the one with the highest title-Jaccard. Unique LSH
        // bands per doc keep stage 3 out of the cascade so stage 4 is
        // reached; the threshold + best-of selection is what produces
        // the verdict.
        $candidateShingles = ['alpha', 'beta', 'gamma', 'delta', 'epsilon'];

        // 3/5 shared with candidate → Jaccard 3/(5+5-3) = 3/7 ≈ 0.43 (below 0.85).
        $low = $this->indexDocument(
            id: 'doc-low',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-low',
                simHash: '0000000000000000',
                lshBands: $this->uniqueBands('best-low'),
                titleShingles: ['alpha', 'beta', 'gamma', 'theta', 'iota'],
            ),
        );

        // All 5 shared → Jaccard 5/5 = 1.0 — should win.
        $high = $this->indexDocument(
            id: 'doc-high',
            canonicalUrl: null,
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-high',
                simHash: '0000000000000000',
                lshBands: $this->uniqueBands('best-high'),
                titleShingles: $candidateShingles,
            ),
        );

        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(
                contentHash: 'sha256-probe',
                simHash: 'ffffffffffffffff',
                lshBands: $this->uniqueBands('best-probe'),
                titleShingles: $candidateShingles,
            ),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::NEAR_DUPLICATE, $result->outcome);
        self::assertSame(DuplicateMatchStage::TITLE_FALLBACK, $result->stage);
        self::assertSame($high->getId(), $result->originalDocumentId);
        self::assertNotSame($low->getId(), $result->originalDocumentId);
        self::assertSame(1.0, $result->similarity);
    }

    public function testEmptyCanonicalUrlBypassesStage0(): void
    {
        // Empty string must be treated as "no canonical URL" — otherwise
        // every freshly-collected document without a canonical URL would
        // hit the gateway with an empty needle.
        $this->indexDocument(
            id: 'doc-blank-canonical',
            canonicalUrl: '',
            fingerprint: $this->makeFingerprint(contentHash: 'sha256-blank'),
        );

        $result = $this->detector->detect(
            fingerprint: $this->makeFingerprint(contentHash: 'sha256-blank'),
            canonicalUrl: '',
        );

        // Stage 0 skipped, but stage 1 catches via contentHash equality.
        self::assertSame(DuplicateMatchStage::CONTENT_HASH, $result->stage);
    }

    private function indexDocument(string $id, ?string $canonicalUrl, Fingerprint $fingerprint): Document
    {
        $document = new Document(
            id: $id,
            title: "title-{$id}",
            excerpt: 'excerpt',
            type: 'article',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'content',
        );
        $document->setCanonicalUrl($canonicalUrl);
        $document->setFingerprint($fingerprint);

        $this->documentGateway->save($document);
        $this->fingerprintGateway->addDocument($document);

        return $document;
    }

    /**
     * @param list<int>|null    $minHashSignature
     * @param list<string>|null $lshBands
     * @param list<string>      $titleShingles
     */
    private function makeFingerprint(
        string $contentHash = 'sha256-default',
        string $simHash = '0000000000000000',
        ?array $minHashSignature = null,
        ?array $lshBands = null,
        array $titleShingles = [],
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
            titleShingles: $titleShingles,
        );
    }

    /**
     * Build a 32-band list with `$sharedBand` planted at `$position` and
     * unique fillers everywhere else.
     *
     * @return list<string>
     */
    private function makeBands(string $sharedBand, int $position): array
    {
        return array_map(
            static fn (int $i): string => $i === $position
                ? $sharedBand
                : str_pad((string) $i, 32, '0', \STR_PAD_LEFT),
            range(0, LshBandGenerator::NUM_BANDS - 1),
        );
    }

    /**
     * Build a 32-band list whose every entry is unique (per-band MD5 of
     * `"{$salt}-{$bandIndex}"`). Used by stage-4 tests to make sure stage
     * 3 finds no candidate via shared LSH bands so the cascade actually
     * reaches the title-fallback branch.
     *
     * @return list<string>
     */
    private function uniqueBands(string $salt): array
    {
        return array_map(
            static fn (int $i): string => hash('md5', "{$salt}-{$i}"),
            range(0, LshBandGenerator::NUM_BANDS - 1),
        );
    }
}
