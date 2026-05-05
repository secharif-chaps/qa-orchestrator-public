<?php

declare(strict_types=1);

namespace App\Tests\Integration\Document;

use App\Domain\Document\Deduplication\DuplicateMatchStage;
use App\Domain\Document\Deduplication\DuplicateOutcome;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\Pipeline\PreSaveDocumentPipelineInterface;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Document\Pipeline\Processor\Deduplication\DuplicateDetectionPreSaveProcessor;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\OpenSearchUtilsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * End-to-end happy-path + edge-case coverage of the entire deduplication
 * pipeline, exercising the real `PreSaveDocumentPipeline` (with every
 * tagged processor) against the real OpenSearch test index.
 *
 * The pipeline run includes:
 *   - canonical URL enrichment (existing)
 *   - the new `DuplicateDetectionPreSaveProcessor` (TAR-1146)
 *
 * Each test indexes some original documents, then runs the pipeline on
 * a candidate and asserts on:
 *   - context state (halted? duplicateOf?)
 *   - candidate state (fingerprint posed when UNIQUE)
 *   - original state (DuplicateAttempt appended on match)
 */
#[CoversClass(DuplicateDetectionPreSaveProcessor::class)]
final class DuplicateDetectionPipelineTest extends KernelTestCase
{
    use EntityUtilsTrait;
    use OpenSearchUtilsTrait;
    private const string TEST_RUN_TAG = 'tar-1146-pipeline-test';
    private PreSaveDocumentPipelineInterface $pipeline;
    private DocumentGatewayInterface $gateway;
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        /** @var PreSaveDocumentPipelineInterface $pipeline */
        $pipeline = self::getContainer()->get(PreSaveDocumentPipelineInterface::class);
        $this->pipeline = $pipeline;

        /** @var DocumentGatewayInterface $gateway */
        $gateway = self::getContainer()->get(DocumentGatewayInterface::class);
        $this->gateway = $gateway;

        $this->watchFile = new WatchFile(
            name: 'Pipeline test wf',
            userObjective: 'integration coverage',
            organisation: new Organisation('Test Org', 'test-org-id-pipeline'),
        );
        $this->forcePropertyValue($this->watchFile, 'wf-pipeline-test');

        $this->cleanupOpenSearchByTag(self::TEST_RUN_TAG);
    }

    protected function tearDown(): void
    {
        $this->cleanupOpenSearchByTag(self::TEST_RUN_TAG);
        parent::tearDown();
    }

    // ─── Happy paths ─────────────────────────────────────────────────────

    #[Test]
    public function uniqueDocumentExitsPipelineWithFingerprintAttached(): void
    {
        $candidate = $this->makeDocument(
            id: 'doc-fresh',
            content: 'A fresh, never-before-seen article about deep-sea ecosystems.',
            url: 'https://example.com/fresh',
        );

        $context = $this->pipeline->process(
            document: $candidate,
            watchFile: $this->watchFile,
            collectTaskId: 'ct-1',
            provider: 'apify',
        );

        self::assertFalse($context->isHalted);
        self::assertNull($context->duplicateOf);
        self::assertNotNull($candidate->getFingerprint());
        self::assertSame(64, \strlen($candidate->getFingerprint()->contentHash));
    }

    #[Test]
    public function recrawlOfSameContentHaltsAndAppendsTraceToOriginal(): void
    {
        $sharedContent = 'Identical wire content recrawled hours later.';

        // First pass — fresh document, gets indexed.
        $first = $this->makeDocument(
            id: 'doc-first-pass',
            content: $sharedContent,
            url: 'https://wire.com/article',
        );
        $this->pipeline->process(
            document: $first,
            watchFile: $this->watchFile,
            collectTaskId: 'ct-1',
            provider: 'apify',
        );
        $this->gateway->save($first);
        $this->refreshOpenSearchIndex();

        // Second pass — same content, different ID + URL: must halt.
        $second = $this->makeDocument(
            id: 'doc-second-pass',
            content: $sharedContent,
            url: 'https://wire.com/article?utm=cron-rerun',
        );
        $context = $this->pipeline->process(
            document: $second,
            watchFile: $this->watchFile,
            collectTaskId: 'ct-2',
            provider: 'bakus',
        );

        self::assertTrue($context->isHalted);
        self::assertSame($first->getId(), $context->duplicateOf);

        $reloaded = $this->gateway->get($first->getId());
        $duplicates = $reloaded->getDuplicates();
        self::assertCount(1, $duplicates);
        self::assertSame(DuplicateOutcome::DUPLICATE, $duplicates[0]->outcome);
        self::assertSame(DuplicateMatchStage::CONTENT_HASH, $duplicates[0]->matchStage);
        self::assertSame('https://wire.com/article?utm=cron-rerun', $duplicates[0]->url);
        self::assertSame('ct-2', $duplicates[0]->collectTaskId);
        self::assertSame('bakus', $duplicates[0]->provider);
    }

    #[Test]
    public function multipleAttemptsOnSameOriginalAccumulateInTrace(): void
    {
        // Three different sources collecting the same article — the
        // original's `duplicates` should hold all three attempts.
        $sharedContent = 'Highly republished article about a major event.';

        $original = $this->makeDocument(
            id: 'doc-original-multi',
            content: $sharedContent,
            url: 'https://afp.com/article',
        );
        $this->pipeline->process(
            document: $original,
            watchFile: $this->watchFile,
            collectTaskId: 'ct-original',
            provider: 'apify',
        );
        $this->gateway->save($original);
        $this->refreshOpenSearchIndex();

        foreach ([
            'repost-A' => 'apify',
            'repost-B' => 'bakus',
            'repost-C' => 'manual',
        ] as $idSuffix => $provider) {
            $repost = $this->makeDocument(
                id: 'doc-' . $idSuffix,
                content: $sharedContent,
                url: 'https://outlet.com/' . $idSuffix,
            );
            $this->pipeline->process(
                document: $repost,
                watchFile: $this->watchFile,
                collectTaskId: 'ct-' . $idSuffix,
                provider: $provider,
            );
            // Each match refreshes the original — pipeline does the save itself.
            $this->refreshOpenSearchIndex();
        }

        $reloaded = $this->gateway->get($original->getId());
        $duplicates = $reloaded->getDuplicates();
        self::assertCount(3, $duplicates);

        $providers = array_map(static fn ($attempt) => $attempt->provider, $duplicates);
        self::assertEqualsCanonicalizing(['apify', 'bakus', 'manual'], $providers);
    }

    #[Test]
    public function canonicalUrlMatchHaltsBeforeFingerprintLookup(): void
    {
        $original = $this->makeDocument(
            id: 'doc-with-canonical',
            content: 'Original content under a canonical URL.',
            url: 'https://canonical.com/article',
        );
        $original->setCanonicalUrl('https://canonical.com/article');
        $this->pipeline->process(
            document: $original,
            watchFile: $this->watchFile,
            collectTaskId: 'ct-canonical-original',
            provider: 'apify',
        );
        $this->gateway->save($original);
        $this->refreshOpenSearchIndex();

        // Different content, same canonical → stage 0 catches it.
        $repost = $this->makeDocument(
            id: 'doc-canonical-repost',
            content: 'Wrapper content with extra editorial comments and a different body entirely.',
            url: 'https://outlet.com/canonical-repost',
        );
        $repost->setCanonicalUrl('https://canonical.com/article');

        $context = $this->pipeline->process(
            document: $repost,
            watchFile: $this->watchFile,
            collectTaskId: 'ct-canonical-repost',
            provider: 'apify',
        );

        self::assertTrue($context->isHalted);
        self::assertSame($original->getId(), $context->duplicateOf);

        $reloaded = $this->gateway->get($original->getId());
        $duplicates = $reloaded->getDuplicates();
        self::assertCount(1, $duplicates);
        self::assertSame(DuplicateMatchStage::CANONICAL_URL, $duplicates[0]->matchStage);
    }

    #[Test]
    public function nearDuplicateTextCaughtInLshStage(): void
    {
        $base = <<<TEXT
            The Eiffel Tower is a wrought-iron lattice tower on the Champ de Mars in Paris, France.
            It is named after the engineer Gustave Eiffel, whose company designed and built the tower.
            Locally nicknamed La dame de fer, it was constructed from 1887 to 1889 as the centrepiece
            of the 1889 World's Fair. It was initially criticised by some of France's leading artists
            and intellectuals for its design, but it has become a global cultural icon of France and
            one of the most recognisable structures in the world. Today the tower receives several
            million visitors every year and remains one of the most-visited paid monuments on Earth.
            TEXT;

        $original = $this->makeDocument(
            id: 'doc-eiffel-original',
            content: $base,
            url: 'https://wiki.com/eiffel',
        );
        $this->pipeline->process(
            document: $original,
            watchFile: $this->watchFile,
            collectTaskId: 'ct-eiffel-1',
            provider: 'apify',
        );
        $this->gateway->save($original);
        $this->refreshOpenSearchIndex();

        // Substantive but minor edit → contentHash diverges, SimHash close,
        // shared LSH bands → caught by stage 3.
        $rewritten = str_replace(
            ['Champ de Mars', 'most-visited paid monuments'],
            ['Champ-de-Mars esplanade', 'most popular paid landmarks'],
            $base,
        );
        $candidate = $this->makeDocument(
            id: 'doc-eiffel-rewritten',
            content: $rewritten,
            url: 'https://outlet.com/eiffel-rewrite',
        );

        $context = $this->pipeline->process(
            document: $candidate,
            watchFile: $this->watchFile,
            collectTaskId: 'ct-eiffel-2',
            provider: 'bakus',
        );

        self::assertTrue($context->isHalted);
        self::assertSame($original->getId(), $context->duplicateOf);

        $reloaded = $this->gateway->get($original->getId());
        $duplicates = $reloaded->getDuplicates();
        self::assertCount(1, $duplicates);
        self::assertSame(DuplicateMatchStage::MIN_HASH_LSH, $duplicates[0]->matchStage);
        // The two substitutions move the SimHash by 8 bits (> the 3-bit
        // NEAR_EXACT threshold) but only flip ~9 % of the MinHash slots
        // (Jaccard ≈ 0.91), so stage 3 falls through to the Jaccard
        // branch — verdict is NEAR_DUPLICATE, not NEAR_EXACT.
        self::assertSame(DuplicateOutcome::NEAR_DUPLICATE, $duplicates[0]->outcome);
        self::assertNotNull($duplicates[0]->similarity);
        self::assertGreaterThan(0.85, $duplicates[0]->similarity);
    }

    // ─── Edge cases ──────────────────────────────────────────────────────

    #[Test]
    public function legacyDocumentWithoutFingerprintIsIgnoredAtStage3(): void
    {
        // Document indexed before fingerprinting shipped: present in the
        // index, may still bubble up via LSH bands (if any), but cannot
        // be verified — must be silently skipped, not crash.
        $legacy = $this->makeDocument(
            id: 'doc-legacy',
            content: 'Pre-fingerprinting content from before TAR-1145.',
            url: 'https://legacy.com/x',
        );
        // No setFingerprint call. Save directly to bypass the pipeline.
        $this->gateway->save($legacy);
        $this->refreshOpenSearchIndex();

        $candidate = $this->makeDocument(
            id: 'doc-after-fingerprinting',
            content: 'Wholly different content that must be UNIQUE.',
            url: 'https://example.com/different',
        );

        $context = $this->pipeline->process(
            document: $candidate,
            watchFile: $this->watchFile,
            collectTaskId: 'ct-after',
            provider: 'apify',
        );

        self::assertFalse($context->isHalted);
        self::assertNull($context->duplicateOf);
    }

    #[Test]
    public function emptyContentDocumentSkipsTheDedupProcessor(): void
    {
        $candidate = $this->makeDocument(id: 'doc-empty', content: '', url: 'https://example.com/empty');

        $context = $this->pipeline->process(
            document: $candidate,
            watchFile: $this->watchFile,
            collectTaskId: 'ct-empty',
            provider: 'apify',
        );

        // Pipeline didn't halt, no fingerprint posed (processor skipped).
        self::assertFalse($context->isHalted);
        self::assertNull($candidate->getFingerprint());
    }

    #[Test]
    public function selfReindexDoesNotMatchItself(): void
    {
        $existing = $this->makeDocument(
            id: 'doc-self',
            content: 'Document already in the index.',
            url: 'https://example.com/self',
        );
        $existing->setCanonicalUrl('https://example.com/self');
        $this->pipeline->process(
            document: $existing,
            watchFile: $this->watchFile,
            collectTaskId: 'ct-first',
            provider: 'apify',
        );
        $this->gateway->save($existing);
        $this->refreshOpenSearchIndex();

        // Run the pipeline again on the same document — exclusion path.
        $context = $this->pipeline->process(
            document: $existing,
            watchFile: $this->watchFile,
            collectTaskId: 'ct-rerun',
            provider: 'apify',
        );

        self::assertFalse($context->isHalted);
        self::assertNull($context->duplicateOf);
    }

    #[Test]
    public function multilingualCjkContentRoundsTripThroughThePipeline(): void
    {
        $cjk = '東京都は日本の首都です。新宿区は東京都の中心地です。渋谷区も東京都にあります。'
            . '東京都の人口は多く、日本の経済の中心地でもあります。';

        $original = $this->makeDocument(id: 'doc-cjk-original', content: $cjk, url: 'https://example.jp/article');
        $this->pipeline->process(
            document: $original,
            watchFile: $this->watchFile,
            collectTaskId: 'ct-cjk-1',
            provider: 'apify',
        );
        $this->gateway->save($original);
        $this->refreshOpenSearchIndex();

        // Same CJK text → stage 1 contentHash match.
        $repost = $this->makeDocument(id: 'doc-cjk-repost', content: $cjk, url: 'https://outlet.jp/repost');
        $context = $this->pipeline->process(
            document: $repost,
            watchFile: $this->watchFile,
            collectTaskId: 'ct-cjk-2',
            provider: 'bakus',
        );

        self::assertTrue($context->isHalted);
        self::assertSame($original->getId(), $context->duplicateOf);

        $reloaded = $this->gateway->get($original->getId());
        self::assertCount(1, $reloaded->getDuplicates());
        self::assertSame(DuplicateMatchStage::CONTENT_HASH, $reloaded->getDuplicates()[0]->matchStage);
    }

    #[Test]
    public function missingCollectMetadataStillHaltsButSkipsTrace(): void
    {
        $sharedContent = 'Content used to test missing-metadata path.';
        $original = $this->makeDocument(
            id: 'doc-orig-missing-meta',
            content: $sharedContent,
            url: 'https://example.com/orig',
        );
        $this->pipeline->process(
            document: $original,
            watchFile: $this->watchFile,
            collectTaskId: 'ct-orig',
            provider: 'apify',
        );
        $this->gateway->save($original);
        $this->refreshOpenSearchIndex();

        // Run pipeline without collectTaskId / provider — match still
        // happens, but recordDuplicate is skipped.
        $candidate = $this->makeDocument(
            id: 'doc-missing-meta',
            content: $sharedContent,
            url: 'https://example.com/missing',
        );
        $context = $this->pipeline->process(
            document: $candidate,
            watchFile: $this->watchFile,
            collectTaskId: null,
            provider: null,
        );

        self::assertTrue($context->isHalted);
        self::assertSame($original->getId(), $context->duplicateOf);

        $reloaded = $this->gateway->get($original->getId());
        // No DuplicateAttempt because metadata was missing.
        self::assertSame([], $reloaded->getDuplicates());
    }

    private function makeDocument(string $id, string $content, ?string $url = null): Document
    {
        // Per-doc unique title — stage 4 (title fallback) would otherwise
        // cross-pollute these tests because every doc would index identical
        // title shingles. We probe stages 0-3 in this suite; stage 4 is
        // covered separately in DuplicateDetectorIntegrationTest.
        $document = new Document(
            id: $id . '-' . self::TEST_RUN_TAG,
            title: 'Pipeline test ' . $id,
            excerpt: 'Excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            dateCollect: new \DateTimeImmutable('2026-01-02T00:00:00Z'),
            content: $content,
            status: DocumentStatus::PENDING,
            url: $url,
        );
        $this->forcePropertyValue($document, $this->watchFile, 'watchFile');

        return $document;
    }
}
