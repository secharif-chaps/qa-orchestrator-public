<?php

declare(strict_types=1);

namespace App\Tests\Integration\Document;

use App\Domain\Document\Deduplication\DuplicateDetector;
use App\Domain\Document\Deduplication\DuplicateMatchStage;
use App\Domain\Document\Deduplication\DuplicateOutcome;
use App\Domain\Document\Deduplication\FingerprintGatewayInterface;
use App\Domain\Document\Deduplication\FingerprintSimilarity;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\Fingerprinting\DocumentFingerprintComputer;
use App\Domain\Document\Fingerprinting\Fingerprint;
use App\Domain\Document\Fingerprinting\LshBandGenerator;
use App\Domain\Document\Fingerprinting\MinHashGenerator;
use App\Domain\Document\Fingerprinting\ScriptDetector;
use App\Domain\Document\Fingerprinting\ShingleExtractor;
use App\Domain\Document\Fingerprinting\SimHashGenerator;
use App\Domain\Document\Fingerprinting\TextNormalizer;
use App\Tests\Utils\OpenSearchUtilsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * End-to-end integration: real fingerprint computation against the real
 * OpenSearch index, walking the four-stage cascade with five scenarios
 * mirroring ADR-2026-006:
 *
 *   - Stage 0 (canonical URL)  : AFP-wire reposted by another outlet
 *   - Stage 1 (content hash)   : same article re-crawled hours later
 *   - Stage 2 (SimHash exact)  : two payloads producing bit-identical SimHash
 *   - Stage 3 (LSH + Hamming)  : article with a typo fix
 *   - UNIQUE                   : genuinely different article
 */
#[CoversClass(DuplicateDetector::class)]
final class DuplicateDetectorIntegrationTest extends KernelTestCase
{
    use OpenSearchUtilsTrait;
    private const string TEST_RUN_TAG = 'tar-1146-detector-test';
    private DuplicateDetector $detector;
    private DocumentFingerprintComputer $computer;
    private DocumentGatewayInterface $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        /** @var DocumentGatewayInterface $gateway */
        $gateway = self::getContainer()->get(DocumentGatewayInterface::class);
        $this->gateway = $gateway;

        /** @var FingerprintGatewayInterface $fingerprintGateway */
        $fingerprintGateway = self::getContainer()->get(FingerprintGatewayInterface::class);

        // Domain services have no infrastructure deps — wire them
        // directly. Pulling them off the container would also work but
        // requires marking them public (Symfony inlines unused aliases).
        $this->computer = new DocumentFingerprintComputer(
            new TextNormalizer(),
            new ShingleExtractor(new ScriptDetector()),
            new SimHashGenerator(),
            new MinHashGenerator(),
            new LshBandGenerator(),
        );
        $this->detector = new DuplicateDetector($this->gateway, $fingerprintGateway, new FingerprintSimilarity());

        $this->cleanupOpenSearchByTag(self::TEST_RUN_TAG);
    }

    protected function tearDown(): void
    {
        $this->cleanupOpenSearchByTag(self::TEST_RUN_TAG);
        parent::tearDown();
    }

    /**
     * Compute a fingerprint and assert it is non-null. Every test case in
     * this suite passes real text producing a non-empty shingle set, so a
     * null return would be a contract violation worth failing on
     * immediately rather than NPE-ing later in the assertions.
     */
    private function computeFingerprint(string $text, string $title = ''): Fingerprint
    {
        $fingerprint = $this->computer->compute($text, $title);
        self::assertNotNull(
            $fingerprint,
            \sprintf('Expected non-null fingerprint for text starting with: %s', mb_substr($text, 0, 60)),
        );

        return $fingerprint;
    }

    #[Test]
    public function stage0CanonicalUrlMatchEvenWhenContentDiffers(): void
    {
        // AFP wire reposted by another outlet: different rendered HTML
        // (different bytes) but the canonical URL points back to AFP.
        $afpText = 'Original AFP wire about a major political event in Europe.';
        $afpFingerprint = $this->computeFingerprint($afpText);
        $original = $this->indexDocument(
            id: 'doc-afp-original',
            canonicalUrl: 'https://www.afp.com/wire/article-12345',
            fingerprint: $afpFingerprint,
        );

        // Outlet's rewrap: same canonical, different content hash.
        $repost = $this->computeFingerprint('Outlet wraps AFP wire with their own commentary added on top.');

        $result = $this->detector->detect(
            fingerprint: $repost,
            canonicalUrl: 'https://www.afp.com/wire/article-12345',
        );

        self::assertSame(DuplicateOutcome::DUPLICATE, $result->outcome);
        self::assertSame(DuplicateMatchStage::CANONICAL_URL, $result->stage);
        self::assertSame($original->getId(), $result->originalDocumentId);
        self::assertSame(1.0, $result->similarity);
    }

    #[Test]
    public function stage1ContentHashMatchOnExactRecrawl(): void
    {
        $text = 'Identical content recrawled by the cron a few hours later — bytes match.';
        $fingerprint = $this->computeFingerprint($text);
        $original = $this->indexDocument(id: 'doc-recrawl', canonicalUrl: null, fingerprint: $fingerprint);

        $result = $this->detector->detect(fingerprint: $this->computeFingerprint($text), canonicalUrl: null);

        self::assertSame(DuplicateOutcome::DUPLICATE, $result->outcome);
        self::assertSame(DuplicateMatchStage::CONTENT_HASH, $result->stage);
        self::assertSame($original->getId(), $result->originalDocumentId);
    }

    #[Test]
    public function stage2SimHashExactMatchOnSyntheticCollision(): void
    {
        // Two natural texts producing the same SimHash are vanishingly
        // rare; we plant a synthetic fingerprint that shares the SimHash
        // but has a different contentHash so stage 1 doesn't catch it.
        $sharedSimHash = 'a1b2c3d4e5f60718';
        /** @var list<int> $signatureA */
        $signatureA = array_fill(0, MinHashGenerator::NUM_HASHES, 7);
        /** @var list<int> $signatureB */
        $signatureB = array_fill(0, MinHashGenerator::NUM_HASHES, 13);
        $bandsA = array_map(
            static fn (int $i): string => md5("a:{$i}"),
            range(0, LshBandGenerator::NUM_BANDS - 1),
        );
        $bandsB = array_map(
            static fn (int $i): string => md5("b:{$i}"),
            range(0, LshBandGenerator::NUM_BANDS - 1),
        );

        $original = $this->indexDocument(
            id: 'doc-simhash-exact',
            canonicalUrl: null,
            fingerprint: new Fingerprint(
                contentHash: hash('sha256', 'original-bytes'),
                simHash: $sharedSimHash,
                minHashSignature: $signatureA,
                lshBands: $bandsA,
            ),
        );

        $result = $this->detector->detect(
            fingerprint: new Fingerprint(
                contentHash: hash('sha256', 'candidate-bytes'),
                simHash: $sharedSimHash,
                minHashSignature: $signatureB,
                lshBands: $bandsB,
            ),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::NEAR_EXACT, $result->outcome);
        self::assertSame(DuplicateMatchStage::SIM_HASH, $result->stage);
        self::assertSame($original->getId(), $result->originalDocumentId);
        self::assertSame(1.0, $result->similarity);
    }

    #[Test]
    public function stage3CatchesParaphrasedTextAsNearDuplicateViaJaccard(): void
    {
        // A long article where two short phrases are paraphrased: the
        // contentHash diverges (stage 1 misses), SimHash moves by ~8
        // bits (Hamming > 3, so the Hamming branch passes), but the
        // MinHash signature still collides on >85 % of slots (Jaccard
        // ≈ 0.91), so stage 3's Jaccard branch fires → NEAR_DUPLICATE.
        $base = <<<TEXT
            The Eiffel Tower is a wrought-iron lattice tower on the Champ de Mars in Paris, France.
            It is named after the engineer Gustave Eiffel, whose company designed and built the tower.
            Locally nicknamed La dame de fer, it was constructed from 1887 to 1889 as the centrepiece
            of the 1889 World's Fair. It was initially criticised by some of France's leading artists
            and intellectuals for its design, but it has become a global cultural icon of France and
            one of the most recognisable structures in the world. Today the tower receives several
            million visitors every year and remains one of the most-visited paid monuments on Earth.
            TEXT;
        $paraphrased = str_replace(
            ['Champ de Mars', 'most-visited paid monuments'],
            ['Champ-de-Mars esplanade', 'most popular paid landmarks'],
            $base,
        );

        $originalFingerprint = $this->computeFingerprint($base);
        $original = $this->indexDocument(id: 'doc-eiffel', canonicalUrl: null, fingerprint: $originalFingerprint);

        $result = $this->detector->detect(fingerprint: $this->computeFingerprint($paraphrased), canonicalUrl: null);

        self::assertSame(DuplicateOutcome::NEAR_DUPLICATE, $result->outcome);
        self::assertSame(DuplicateMatchStage::MIN_HASH_LSH, $result->stage);
        self::assertSame($original->getId(), $result->originalDocumentId);
        self::assertNotNull($result->similarity);
        self::assertGreaterThan(0.85, $result->similarity);
    }

    #[Test]
    public function genuinelyUniqueDocumentReturnsUnique(): void
    {
        $this->indexDocument(
            id: 'doc-corpus-noise',
            canonicalUrl: null,
            fingerprint: $this->computeFingerprint(
                'Quantum mechanics describes the physical properties of nature at the scale of atoms.',
            ),
        );

        $result = $this->detector->detect(
            fingerprint: $this->computeFingerprint(
                'Le marché de la baguette parisienne reste stable malgré la hausse du blé.',
            ),
            canonicalUrl: null,
        );

        self::assertSame(DuplicateOutcome::UNIQUE, $result->outcome);
        self::assertNull($result->stage);
        self::assertNull($result->originalDocumentId);
    }

    #[Test]
    public function reIndexingTheSameDocumentDoesNotMatchItself(): void
    {
        // The exclusion path is critical: re-running the dedup check on
        // an existing document (e.g. quality re-scoring) must not flag
        // it as a duplicate of itself.
        $text = 'Self-match guard: this document is in the index already.';
        $fingerprint = $this->computeFingerprint($text);
        $self = $this->indexDocument(
            id: 'doc-self-match',
            canonicalUrl: 'https://example.com/self',
            fingerprint: $fingerprint,
        );

        $result = $this->detector->detect(
            fingerprint: $fingerprint,
            canonicalUrl: 'https://example.com/self',
            excludeDocumentId: $self->getId(),
        );

        self::assertSame(DuplicateOutcome::UNIQUE, $result->outcome);
    }

    #[Test]
    public function stage4TitleFallbackMatchesWhenBodiesDivergeButTitlesAreIdentical(): void
    {
        // News-syndication pattern: same headline reposted by another
        // outlet with a fully rewritten body (Jaccard < 0.05). Stages 0-3
        // bail out, stage 4 catches via the shared title-shingle set.
        $title = 'Vestas receives an order for 65 MW wind farm in India';
        $masterBody = 'Vestas Wind Systems A/S today announced an agreement '
            . 'with a customer in India for the supply, installation and '
            . 'commissioning of a 65 megawatt wind farm. Delivery scheduled '
            . 'for Q3. The contract includes a long-term service plan.';
        $candidateBody = 'In a separate press release, the Indian project '
            . 'developer confirmed the deal for the upcoming 65MW capacity '
            . 'unit. Industry analysts welcomed the announcement as a sign '
            . 'of continued momentum in the South Asian renewable market.';

        $masterFp = $this->computeFingerprint($masterBody, $title);
        $original = $this->indexDocument(
            id: 'doc-stage4-master',
            canonicalUrl: 'https://vestas.com/press/65mw-india',
            fingerprint: $masterFp,
            title: $title,
        );

        $candidateFp = $this->computeFingerprint($candidateBody, $title);
        $result = $this->detector->detect(
            fingerprint: $candidateFp,
            canonicalUrl: null,
            excludeDocumentId: 'doc-stage4-candidate',
        );

        self::assertSame(DuplicateOutcome::NEAR_DUPLICATE, $result->outcome);
        self::assertSame(DuplicateMatchStage::TITLE_FALLBACK, $result->stage);
        self::assertSame($original->getId(), $result->originalDocumentId);
        self::assertNotNull($result->similarity);
        self::assertGreaterThanOrEqual(0.70, $result->similarity);
    }

    private function indexDocument(
        string $id,
        ?string $canonicalUrl,
        Fingerprint $fingerprint,
        ?string $title = null,
    ): Document {
        $document = new Document(
            id: $id . '-' . self::TEST_RUN_TAG,
            title: $title ?? 'Integration test document',
            excerpt: 'Excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            dateCollect: new \DateTimeImmutable('2026-01-02T00:00:00Z'),
            content: 'Content',
            status: DocumentStatus::PENDING,
        );
        $document->setCanonicalUrl($canonicalUrl);
        $document->setFingerprint($fingerprint);

        $this->gateway->save($document);
        $this->refreshOpenSearchIndex();

        return $document;
    }
}
