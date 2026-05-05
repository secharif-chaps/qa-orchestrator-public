<?php

declare(strict_types=1);

namespace App\Tests\Integration\Document;

use App\Domain\Document\Deduplication\DuplicateAttempt;
use App\Domain\Document\Deduplication\DuplicateMatchStage;
use App\Domain\Document\Deduplication\DuplicateOutcome;
use App\Domain\Document\Deduplication\FingerprintGatewayInterface;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\Fingerprinting\Fingerprint;
use App\Domain\Document\Fingerprinting\LshBandGenerator;
use App\Domain\Document\Fingerprinting\MinHashGenerator;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test: round-trips Documents bearing a {@see Fingerprint} through
 * the real OpenSearch test index to verify that the dedup pipeline lookups
 * (`findByContentHash`, `findBySimHash`, `findByLshBands`) return the
 * expected documents and honour the `excludeDocumentId` filter, plus
 * confirms that {@see DuplicateAttempt} entries round-trip via the
 * `duplicates` nested mapping.
 */
#[CoversClass(\App\Infrastructure\Document\DocumentOpenSearchGateway::class)]
final class DocumentFingerprintGatewayTest extends KernelTestCase
{
    private const string TEST_RUN_TAG = 'tar-1145-fingerprint-test';
    private DocumentGatewayInterface $gateway;
    private FingerprintGatewayInterface $fingerprintGateway;
    private Client $openSearch;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        /** @var DocumentGatewayInterface $gateway */
        $gateway = self::getContainer()->get(DocumentGatewayInterface::class);
        $this->gateway = $gateway;

        /** @var FingerprintGatewayInterface $fingerprintGateway */
        $fingerprintGateway = self::getContainer()->get(FingerprintGatewayInterface::class);
        $this->fingerprintGateway = $fingerprintGateway;

        /** @var Client $client */
        $client = self::getContainer()->get(Client::class);
        $this->openSearch = $client;

        $this->cleanupTestDocuments();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestDocuments();
        parent::tearDown();
    }

    // ─── findByContentHash ───────────────────────────────────────────────

    #[Test]
    public function findsDocumentByContentHashExactMatch(): void
    {
        $hash = $this->makeContentHash('content-1');
        $document = $this->indexDocument(
            id: 'doc-cthash-1',
            fingerprint: $this->makeFingerprint(contentHash: $hash),
        );

        $found = $this->fingerprintGateway->findByContentHash($hash);

        self::assertNotNull($found);
        self::assertSame($document->getId(), $found->getId());
        self::assertNotNull($found->getFingerprint());
        self::assertSame($hash, $found->getFingerprint()->contentHash);
    }

    #[Test]
    public function returnsNullWhenContentHashIsNotIndexed(): void
    {
        $found = $this->fingerprintGateway->findByContentHash($this->makeContentHash('absent'));

        self::assertNull($found);
    }

    #[Test]
    public function excludesGivenDocumentIdFromContentHashLookup(): void
    {
        $hash = $this->makeContentHash('shared');
        $original = $this->indexDocument('doc-cthash-original', $this->makeFingerprint(contentHash: $hash));
        $duplicate = $this->indexDocument('doc-cthash-duplicate', $this->makeFingerprint(contentHash: $hash));

        $found = $this->fingerprintGateway->findByContentHash($hash, excludeDocumentId: $duplicate->getId());

        self::assertNotNull($found);
        self::assertSame($original->getId(), $found->getId());
    }

    // ─── findBySimHash ───────────────────────────────────────────────────

    #[Test]
    public function findsDocumentsBySimHashExactMatch(): void
    {
        $simHash = $this->makeSimHash('simhash-1');
        $document = $this->indexDocument('doc-simhash-1', $this->makeFingerprint(simHash: $simHash));

        $found = $this->fingerprintGateway->findBySimHash($simHash);

        self::assertCount(1, $found);
        self::assertSame($document->getId(), $found[0]->getId());
    }

    #[Test]
    public function returnsEmptyArrayWhenNoSimHashMatch(): void
    {
        $found = $this->fingerprintGateway->findBySimHash($this->makeSimHash('nope'));

        self::assertSame([], $found);
    }

    #[Test]
    public function excludesGivenDocumentIdFromSimHashLookup(): void
    {
        $simHash = $this->makeSimHash('shared');
        $a = $this->indexDocument('doc-simhash-a', $this->makeFingerprint(simHash: $simHash));
        $b = $this->indexDocument('doc-simhash-b', $this->makeFingerprint(simHash: $simHash));

        $found = $this->fingerprintGateway->findBySimHash($simHash, excludeDocumentId: $a->getId());

        self::assertCount(1, $found);
        self::assertSame($b->getId(), $found[0]->getId());
    }

    // ─── findByLshBands ──────────────────────────────────────────────────

    #[Test]
    public function findsDocumentsSharingAtLeastOneLshBand(): void
    {
        $bandsFull = $this->makeBands('seed');
        $bandsShared = $this->makeBands('seed');
        // Replace all but one band so only `bandsShared[0] === bandsFull[0]`
        // — the single shared band must be enough to surface the doc.
        for ($i = 1; $i < LshBandGenerator::NUM_BANDS; ++$i) {
            $bandsShared[$i] = str_repeat(dechex($i % 16), 32);
        }

        $matching = $this->indexDocument('doc-lsh-matching', $this->makeFingerprint(lshBands: $bandsFull));
        // Disjoint bands derived from a different seed → no overlap with `bandsShared`.
        $disjoint = $this->indexDocument(
            'doc-lsh-disjoint',
            $this->makeFingerprint(lshBands: $this->makeBands('totally-different-seed')),
        );

        $found = $this->fingerprintGateway->findByLshBands(array_values($bandsShared));

        $foundIds = array_map(static fn (Document $d): string => $d->getId(), $found);
        self::assertContains($matching->getId(), $foundIds);
        self::assertNotContains($disjoint->getId(), $foundIds);
    }

    #[Test]
    public function returnsEmptyArrayWhenLshBandsListIsEmpty(): void
    {
        $found = $this->fingerprintGateway->findByLshBands([]);

        self::assertSame([], $found);
    }

    #[Test]
    public function returnsEmptyArrayWhenNoLshBandMatches(): void
    {
        $this->indexDocument('doc-lsh-isolated', $this->makeFingerprint(lshBands: $this->makeBands('seed-a')));

        $found = $this->fingerprintGateway->findByLshBands($this->makeBands('seed-z-completely-different'));

        self::assertSame([], $found);
    }

    #[Test]
    public function excludesGivenDocumentIdFromLshBandLookup(): void
    {
        $bands = $this->makeBands('shared-seed');
        $self = $this->indexDocument('doc-lsh-self', $this->makeFingerprint(lshBands: $bands));
        $other = $this->indexDocument('doc-lsh-other', $this->makeFingerprint(lshBands: $bands));

        $found = $this->fingerprintGateway->findByLshBands($bands, excludeDocumentId: $self->getId());

        $foundIds = array_map(static fn (Document $d): string => $d->getId(), $found);
        self::assertSame([$other->getId()], $foundIds);
    }

    #[Test]
    public function respectsLimitOnLshBandLookup(): void
    {
        $bands = $this->makeBands('limit-seed');
        for ($i = 0; $i < 5; ++$i) {
            $this->indexDocument("doc-lsh-limit-{$i}", $this->makeFingerprint(lshBands: $bands));
        }

        $found = $this->fingerprintGateway->findByLshBands($bands, limit: 2);

        self::assertCount(2, $found);
    }

    // ─── Document.duplicates round-trip ──────────────────────────────────

    #[Test]
    public function roundTripsDuplicateAttemptsThroughOpenSearch(): void
    {
        $document = new Document(
            id: 'doc-duplicates-roundtrip-' . self::TEST_RUN_TAG,
            title: 'Original',
            excerpt: 'Excerpt',
            type: 'article',
            datePublish: new \DateTimeImmutable('2026-04-01T00:00:00Z'),
            dateCollect: new \DateTimeImmutable('2026-04-01T00:00:00Z'),
            content: 'Body',
            status: DocumentStatus::PENDING,
        );
        $document->setFingerprint($this->makeFingerprint(contentHash: $this->makeContentHash('with-duplicates')));
        $document->recordDuplicate(new DuplicateAttempt(
            url: 'https://lemonde.fr/republished/' . self::TEST_RUN_TAG,
            watchFileId: 'wf-rt',
            collectTaskId: 'ct-rt',
            sourceId: 'src-rt',
            provider: 'apify',
            collectedAt: new \DateTimeImmutable('2026-04-02T10:00:00Z'),
            outcome: DuplicateOutcome::DUPLICATE,
            matchStage: DuplicateMatchStage::CANONICAL_URL,
            similarity: 1.0,
        ));
        $this->gateway->save($document);
        $this->refreshDocumentIndex();

        $reloaded = $this->gateway->get($document->getId());

        $duplicates = $reloaded->getDuplicates();
        self::assertCount(1, $duplicates);
        self::assertSame('https://lemonde.fr/republished/' . self::TEST_RUN_TAG, $duplicates[0]->url);
        self::assertSame('wf-rt', $duplicates[0]->watchFileId);
        self::assertSame('ct-rt', $duplicates[0]->collectTaskId);
        self::assertSame('apify', $duplicates[0]->provider);
        self::assertSame(DuplicateOutcome::DUPLICATE, $duplicates[0]->outcome);
        self::assertSame(DuplicateMatchStage::CANONICAL_URL, $duplicates[0]->matchStage);
        self::assertSame(1.0, $duplicates[0]->similarity);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function indexDocument(string $id, Fingerprint $fingerprint): Document
    {
        $document = new Document(
            id: $id . '-' . self::TEST_RUN_TAG,
            title: 'Integration test document',
            excerpt: 'Excerpt for fingerprint integration test',
            type: 'html',
            datePublish: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            dateCollect: new \DateTimeImmutable('2026-01-02T00:00:00Z'),
            content: '<p>Test content for fingerprint integration test.</p>',
            status: DocumentStatus::PENDING,
        );
        $document->setFingerprint($fingerprint);
        $this->gateway->save($document);
        $this->refreshDocumentIndex();

        return $document;
    }

    private function makeContentHash(string $seed): string
    {
        // Mirror the production digest size (SHA256 hex = 64 chars).
        return hash('sha256', self::TEST_RUN_TAG . ':' . $seed);
    }

    private function makeSimHash(string $seed): string
    {
        // 16 hex chars = 64 bits, matching SimHashGenerator output.
        return substr(hash('xxh3', self::TEST_RUN_TAG . ':' . $seed), 0, 16);
    }

    /**
     * @return list<string>
     */
    private function makeBands(string $seed): array
    {
        $bands = [];
        for ($i = 0; $i < LshBandGenerator::NUM_BANDS; ++$i) {
            $bands[] = md5(self::TEST_RUN_TAG . ':' . $seed . ':' . $i);
        }

        return $bands;
    }

    /**
     * @param ?list<int>    $minHashSignature
     * @param ?list<string> $lshBands
     */
    private function makeFingerprint(
        ?string $contentHash = null,
        ?string $simHash = null,
        ?array $minHashSignature = null,
        ?array $lshBands = null,
    ): Fingerprint {
        return new Fingerprint(
            contentHash: $contentHash ?? $this->makeContentHash('default'),
            simHash: $simHash ?? $this->makeSimHash('default'),
            minHashSignature: $minHashSignature ?? array_fill(0, MinHashGenerator::NUM_HASHES, 0),
            lshBands: $lshBands ?? $this->makeBands('default'),
        );
    }

    private function refreshDocumentIndex(): void
    {
        $this->openSearch->indices()
            ->refresh([
                'index' => Document::INDEX_NAME,
            ]);
    }

    private function cleanupTestDocuments(): void
    {
        try {
            $this->openSearch->deleteByQuery([
                'index' => Document::INDEX_NAME,
                'body' => [
                    'query' => [
                        'wildcard' => [
                            'id' => '*' . self::TEST_RUN_TAG . '*',
                        ],
                    ],
                ],
                'refresh' => true,
                'conflicts' => 'proceed',
            ]);
        } catch (\Exception) {
            // Cleanup is best-effort; failures here must not mask the actual test result.
        }
    }
}
