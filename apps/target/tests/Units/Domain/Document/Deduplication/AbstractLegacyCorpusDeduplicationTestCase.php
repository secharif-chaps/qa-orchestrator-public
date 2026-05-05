<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Deduplication;

use App\Domain\Document\CanonicalUrlExtractor;
use App\Domain\Document\Deduplication\DuplicateDetector;
use App\Domain\Document\Deduplication\DuplicateMatchStage;
use App\Domain\Document\Deduplication\DuplicateOutcome;
use App\Domain\Document\Deduplication\FingerprintSimilarity;
use App\Domain\Document\Document;
use App\Domain\Document\Fingerprinting\DocumentFingerprintComputer;
use App\Domain\Document\Fingerprinting\Fingerprint;
use App\Domain\Document\Fingerprinting\LshBandGenerator;
use App\Domain\Document\Fingerprinting\MinHashGenerator;
use App\Domain\Document\Fingerprinting\ScriptDetector;
use App\Domain\Document\Fingerprinting\ShingleExtractor;
use App\Domain\Document\Fingerprinting\SimHashGenerator;
use App\Domain\Document\Fingerprinting\TextNormalizer;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use App\Tests\Units\Infrastructure\Document\NullFingerprintGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Golden test: replays a corpus of real duplicate pairs extracted from
 * the legacy AMI database (account 26) — pulled out of the
 * "Duplicates Age Analysis" Grafana dashboard — and asserts that our
 * detector still produces the **same** outcome, stage and similarity
 * captured at fixture-generation time.
 *
 * The fixture lives at `tests/resources/Deduplication/legacy-dedup-cases.json`
 * and is regenerated via `tests/resources/Deduplication/generate-legacy-fixtures.php`
 * from a CSV export. Concrete numbers (Hamming, Jaccard) for each pair
 * are *characterizations*, not normative — but pinning them turns any
 * algorithm change (threshold, hash function, normaliser) into an
 * explicit, case-by-case test failure rather than silent drift on a
 * live data path.
 *
 * Concordance with the legacy verdict is **not** asserted: the legacy
 * AMI dedup uses a different algorithm (Pure / Mirror / Similar levels)
 * and naturally diverges on fuzzy cases. The legacy outcome is kept in
 * the fixture for context but only the `pinned` field drives assertions.
 */
#[CoversClass(DuplicateDetector::class)]
#[CoversClass(DocumentFingerprintComputer::class)]
abstract class AbstractLegacyCorpusDeduplicationTestCase extends TestCase
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

    /**
     * @param array{id: string, url: string, title: string, content: string} $master
     * @param array{id: string, url: string, title: string, content: string} $candidate
     * @param array{outcome: string, stage: ?string, similarity: ?float}     $pinned
     */
    #[DataProvider('legacyCorpus')]
    #[Group('slow')]
    public function testReplayingALegacyCaseProducesThePinnedVerdict(
        string $legacyOutcome,
        int $legacyLevel,
        array $master,
        array $candidate,
        array $pinned,
    ): void {
        $documentGateway = new NullDocumentGateway();
        $fingerprintGateway = new NullFingerprintGateway();
        $detector = new DuplicateDetector($documentGateway, $fingerprintGateway, new FingerprintSimilarity());

        // Mirror the production save pipeline: URLs are stored in the
        // index and probed in their canonicalised form (lowercased,
        // IDN-encoded, no tracking params, no trailing slash on
        // non-root paths). Without this normalisation, a master indexed
        // as `…/join-us/` would not match a candidate at `…/join-us`
        // even though they resolve to the same document.
        $extractor = new CanonicalUrlExtractor();
        $masterUrl = '' !== $master['url'] ? $extractor->canonicalize($master['url']) : null;
        $candidateUrl = '' !== $candidate['url'] ? $extractor->canonicalize($candidate['url']) : null;

        $masterDocument = $this->makeDocument(
            id: $master['id'],
            title: $master['title'],
            content: $master['content'],
            url: $masterUrl,
        );
        $masterFingerprint = $this->fingerprintOf($master['content'], $master['title']);
        if (null !== $masterFingerprint) {
            $masterDocument->setFingerprint($masterFingerprint);
        }
        if (null !== $masterUrl) {
            $masterDocument->setCanonicalUrl($masterUrl);
        }
        $documentGateway->save($masterDocument);
        $fingerprintGateway->addDocument($masterDocument);

        $result = $detector->detect(
            fingerprint: $this->fingerprintOf($candidate['content'], $candidate['title']),
            canonicalUrl: $candidateUrl,
            excludeDocumentId: $candidate['id'],
        );

        $context = \sprintf('[legacy=%s level=%d master=%s]', $legacyOutcome, $legacyLevel, $master['id']);

        self::assertSame(
            DuplicateOutcome::from($pinned['outcome']),
            $result->outcome,
            "Outcome drift {$context}",
        );

        self::assertSame(
            null !== $pinned['stage'] ? DuplicateMatchStage::from($pinned['stage']) : null,
            $result->stage,
            "Stage drift {$context}",
        );

        if (null === $pinned['similarity']) {
            self::assertNull($result->similarity, "Similarity drift {$context}");
        } else {
            self::assertNotNull($result->similarity, "Similarity drift {$context}");
            self::assertEqualsWithDelta(
                $pinned['similarity'],
                $result->similarity,
                0.001,
                "Similarity drift {$context}",
            );
        }
    }

    /**
     * Each subclass returns the legacy account id whose case files it
     * exercises. Splitting per-account turns each subclass into an
     * independent test suite that paratest can run in parallel:
     *
     *   vendor/bin/paratest --processes=7 tests/Units/Domain/Document/Deduplication/
     *
     * Measured: ~2 min sequential → ~1 min on 7 workers (the largest
     * accounts — 64 with 288 cases and 204 with 243 cases — set the
     * floor; further parallelism would require splitting those
     * accounts in halves).
     */
    abstract protected static function accountId(): string;

    /**
     * @return iterable<string, array{
     *     legacyOutcome: string,
     *     legacyLevel: int,
     *     master: array{id: string, url: string, title: string, content: string},
     *     candidate: array{id: string, url: string, title: string, content: string},
     *     pinned: array{outcome: string, stage: ?string, similarity: ?float}
     * }>
     */
    final public static function legacyCorpus(): iterable
    {
        $casesDir = __DIR__ . '/../../../../resources/Deduplication/cases/' . static::accountId();
        $files = glob($casesDir . '/*.json');
        if (false === $files || [] === $files) {
            throw new \RuntimeException("No legacy case files under {$casesDir}");
        }
        sort($files);

        foreach ($files as $i => $path) {
            $raw = file_get_contents($path);
            if (false === $raw) {
                throw new \RuntimeException("Cannot read {$path}");
            }
            /** @var array{
             *     legacy_outcome: string,
             *     legacy_level: int,
             *     master: array{id: string, url: string, title: string, content: string},
             *     candidate: array{id: string, url: string, title: string, content: string},
             *     pinned: array{outcome: string, stage: ?string, similarity: ?float}
             * } $c */
            $c = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);

            $key = \sprintf(
                '#%03d %s/%s → %s',
                $i,
                $c['legacy_outcome'],
                $c['master']['id'],
                $c['pinned']['outcome']
            );
            yield $key => [
                'legacyOutcome' => $c['legacy_outcome'],
                'legacyLevel' => $c['legacy_level'],
                'master' => $c['master'],
                'candidate' => $c['candidate'],
                'pinned' => $c['pinned'],
            ];
        }
    }

    private function fingerprintOf(string $content, string $title = ''): ?Fingerprint
    {
        // Mirrors the prod pre-save processor: an all-zero fingerprint
        // from empty content would collide with every other body-less
        // doc in the index. The title feeds stage 4 (title fallback).
        return '' !== $content ? $this->computer->compute($content, $title) : null;
    }

    private function makeDocument(string $id, string $title, string $content, ?string $url): Document
    {
        return new Document(
            id: $id,
            title: '' !== $title ? $title : 'Untitled',
            excerpt: '',
            type: 'article',
            datePublish: new \DateTimeImmutable('2026-01-01'),
            dateCollect: new \DateTimeImmutable('2026-01-02'),
            content: $content,
            url: $url,
        );
    }
}
