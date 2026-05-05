<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document\Pipeline\Processor\Deduplication;

use App\Domain\Document\CanonicalUrlExtractor;
use App\Domain\Document\Deduplication\DuplicateDetector;
use App\Domain\Document\Deduplication\DuplicateMatchStage;
use App\Domain\Document\Deduplication\DuplicateOutcome;
use App\Domain\Document\Deduplication\FingerprintSimilarity;
use App\Domain\Document\Document;
use App\Domain\Document\Fingerprinting\DocumentFingerprintComputer;
use App\Domain\Document\Fingerprinting\LshBandGenerator;
use App\Domain\Document\Fingerprinting\MinHashGenerator;
use App\Domain\Document\Fingerprinting\ScriptDetector;
use App\Domain\Document\Fingerprinting\ShingleExtractor;
use App\Domain\Document\Fingerprinting\SimHashGenerator;
use App\Domain\Document\Fingerprinting\TextNormalizer;
use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Document\Pipeline\Processor\Deduplication\DuplicateDetectionPreSaveProcessor;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use App\Tests\Units\Infrastructure\Document\NullFingerprintGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Cover the pre-save dedup processor: UNIQUE happy path posting the
 * fingerprint, every match-outcome path halting + recording the
 * attempt on the original, and the supports() guards (halted context,
 * empty content).
 */
#[CoversClass(DuplicateDetectionPreSaveProcessor::class)]
class DuplicateDetectionPreSaveProcessorTest extends TestCase
{
    use EntityUtilsTrait;
    private DocumentFingerprintComputer $computer;
    private NullDocumentGateway $documentGateway;
    private NullFingerprintGateway $fingerprintGateway;
    private DuplicateDetector $detector;
    private DuplicateDetectionPreSaveProcessor $processor;
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->computer = new DocumentFingerprintComputer(
            new TextNormalizer(),
            new ShingleExtractor(new ScriptDetector()),
            new SimHashGenerator(),
            new MinHashGenerator(),
            new LshBandGenerator(),
        );
        $this->documentGateway = new NullDocumentGateway();
        $this->fingerprintGateway = new NullFingerprintGateway();
        $this->detector = new DuplicateDetector(
            $this->documentGateway,
            $this->fingerprintGateway,
            new FingerprintSimilarity(),
        );
        $this->processor = new DuplicateDetectionPreSaveProcessor(
            $this->computer,
            $this->detector,
            $this->documentGateway,
            new CanonicalUrlExtractor(),
        );

        $this->watchFile = new WatchFile(
            name: 'Test watch file',
            userObjective: 'Test objective',
            organisation: new Organisation('Test Org', 'test-org-id'),
        );
        $this->forcePropertyValue($this->watchFile, 'wf-test');
    }

    public function testUniquePostsFingerprintAndDoesNotHalt(): void
    {
        $document = $this->makeDocument(
            id: 'doc-new',
            content: 'A genuinely unique article about quantum chromodynamics.'
        );

        $context = $this->processor->process($this->makeContext(document: $document));

        self::assertFalse($context->isHalted);
        self::assertNull($context->duplicateOf);
        self::assertNotNull($document->getFingerprint());
        self::assertSame(64, \strlen($document->getFingerprint()->contentHash));
    }

    public function testStage0CanonicalUrlMatchHaltsAndRecordsAttempt(): void
    {
        $original = $this->indexedOriginal(
            id: 'doc-original',
            canonicalUrl: 'https://example.com/article',
            content: 'Original content here.',
        );

        $candidate = $this->makeDocument(
            id: 'doc-candidate',
            content: 'Different content but same canonical URL.',
            url: 'https://outlet.com/republished',
        );
        $candidate->setCanonicalUrl('https://example.com/article');

        $context = $this->processor->process(
            $this->makeContext(document: $candidate, canonicalUrl: 'https://example.com/article'),
        );

        self::assertTrue($context->isHalted);
        self::assertSame($original->getId(), $context->duplicateOf);
        self::assertNotNull($context->haltReason);

        $duplicates = $original->getDuplicates();
        self::assertCount(1, $duplicates);
        self::assertSame(DuplicateOutcome::DUPLICATE, $duplicates[0]->outcome);
        self::assertSame(DuplicateMatchStage::CANONICAL_URL, $duplicates[0]->matchStage);
        self::assertSame('https://outlet.com/republished', $duplicates[0]->url);
        self::assertSame('ct-test', $duplicates[0]->collectTaskId);
        self::assertSame('apify', $duplicates[0]->provider);
    }

    public function testStage1ContentHashMatchHaltsAndRecordsAttempt(): void
    {
        $sharedContent = 'Identical content recrawled by the cron job.';
        $original = $this->indexedOriginal(id: 'doc-original', canonicalUrl: null, content: $sharedContent);

        $candidate = $this->makeDocument(id: 'doc-candidate', content: $sharedContent, url: 'https://recrawl.com/x');

        $context = $this->processor->process($this->makeContext(document: $candidate));

        self::assertTrue($context->isHalted);
        self::assertSame($original->getId(), $context->duplicateOf);
        self::assertSame(DuplicateMatchStage::CONTENT_HASH, $original->getDuplicates()[0]->matchStage);
    }

    public function testMatchSkipsRecordingWhenCollectMetadataIsMissing(): void
    {
        $sharedContent = 'Same article, missing metadata path.';
        $original = $this->indexedOriginal(id: 'doc-original', canonicalUrl: null, content: $sharedContent);

        $candidate = $this->makeDocument(id: 'doc-candidate', content: $sharedContent, url: 'https://x.com/a');

        // Context with collectTaskId / provider null → halt should still
        // happen (it's the verdict that matters), but no DuplicateAttempt
        // is recorded since required fields would be null.
        $context = $this->processor->process(
            $this->makeContext(document: $candidate, collectTaskId: null, provider: null),
        );

        self::assertTrue($context->isHalted);
        self::assertSame($original->getId(), $context->duplicateOf);
        self::assertSame([], $original->getDuplicates());
    }

    public function testMatchSkipsRecordingWhenCandidateHasNoUrl(): void
    {
        $sharedContent = 'Same article, no URL on candidate.';
        $original = $this->indexedOriginal(id: 'doc-original', canonicalUrl: null, content: $sharedContent);

        $candidate = $this->makeDocument(id: 'doc-candidate', content: $sharedContent, url: null);

        $context = $this->processor->process($this->makeContext(document: $candidate));

        self::assertTrue($context->isHalted);
        self::assertSame([], $original->getDuplicates());
    }

    public function testReindexingTheSameDocumentDoesNotMatchItself(): void
    {
        // Re-running the pipeline on an already-indexed document must not
        // flag it as a duplicate of itself — the excludeDocumentId path.
        $self = $this->indexedOriginal(
            id: 'doc-self',
            canonicalUrl: 'https://example.com/x',
            content: 'Re-indexed content.',
        );
        $self->setCanonicalUrl('https://example.com/x');

        $context = $this->processor->process(
            $this->makeContext(document: $self, canonicalUrl: 'https://example.com/x'),
        );

        self::assertFalse($context->isHalted);
        self::assertNull($context->duplicateOf);
    }

    public function testSupportsReturnsFalseWhenContextIsAlreadyHalted(): void
    {
        $document = $this->makeDocument(id: 'doc-1', content: 'Some content.');
        $context = $this->makeContext(document: $document)
            ->withHalt(new \App\Domain\Shared\TranslatedText(fr: 'Halt', en: 'Halt'));

        self::assertFalse($this->processor->supports($context));
    }

    public function testSupportsReturnsFalseWhenBothContentAndCanonicalUrlAreAbsent(): void
    {
        $document = $this->makeDocument(id: 'doc-empty', content: '');

        self::assertFalse($this->processor->supports($this->makeContext(document: $document)));
    }

    public function testSupportsReturnsTrueWhenContentIsEmptyButCanonicalUrlIsPresent(): void
    {
        // Doc with a canonical tag but no body — stage 0 alone can still
        // catch a known duplicate. Skipping the processor here would
        // mean accepting a re-import of an already-indexed wire.
        $document = $this->makeDocument(id: 'doc-empty', content: '');
        $document->setCanonicalUrl('https://example.com/article');

        self::assertTrue($this->processor->supports($this->makeContext(document: $document)));
    }

    public function testEmptyContentWithCanonicalUrlMatchHaltsAtStage0(): void
    {
        $original = $this->indexedOriginal(
            id: 'doc-original',
            canonicalUrl: 'https://example.com/article',
            content: 'Original body so the original itself has a fingerprint.',
        );

        // Candidate has no body — only the canonical tag — but stage 0
        // should still match it against the original.
        $candidate = $this->makeDocument(id: 'doc-empty-body', content: '', url: 'https://outlet.com/repost');
        $candidate->setCanonicalUrl('https://example.com/article');

        $context = $this->processor->process(
            $this->makeContext(document: $candidate, canonicalUrl: 'https://example.com/article'),
        );

        self::assertTrue($context->isHalted);
        self::assertSame($original->getId(), $context->duplicateOf);
        self::assertCount(1, $original->getDuplicates());
        self::assertSame(DuplicateMatchStage::CANONICAL_URL, $original->getDuplicates()[0]->matchStage);
        // Candidate should not have a fingerprint posed (no body to compute from).
        self::assertNull($candidate->getFingerprint());
    }

    public function testSupportsReturnsTrueOnHappyPath(): void
    {
        $document = $this->makeDocument(id: 'doc-1', content: 'Some content.');

        self::assertTrue($this->processor->supports($this->makeContext(document: $document)));
    }

    private function makeContext(
        Document $document,
        ?string $canonicalUrl = null,
        ?string $collectTaskId = 'ct-test',
        ?string $provider = 'apify',
    ): DocumentPipelineContext {
        return new DocumentPipelineContext(
            document: $document,
            watchFile: $this->watchFile,
            canonicalUrl: $canonicalUrl,
            collectTaskId: $collectTaskId,
            provider: $provider,
        );
    }

    private function makeDocument(string $id, string $content, ?string $url = 'https://example.com/x'): Document
    {
        $document = new Document(
            id: $id,
            title: 'Title',
            excerpt: 'Excerpt',
            type: 'article',
            datePublish: new \DateTimeImmutable('2026-01-01'),
            dateCollect: new \DateTimeImmutable('2026-01-02'),
            content: $content,
            url: $url,
        );

        return $document;
    }

    private function indexedOriginal(string $id, ?string $canonicalUrl, string $content): Document
    {
        $original = $this->makeDocument(id: $id, content: $content);
        if (null !== $canonicalUrl) {
            $original->setCanonicalUrl($canonicalUrl);
        }
        $original->setFingerprint($this->computer->compute($content));

        $this->documentGateway->save($original);
        $this->fingerprintGateway->addDocument($original);

        return $original;
    }
}
