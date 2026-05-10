<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document;

use App\Domain\Document\Deduplication\DuplicateAttempt;
use App\Domain\Document\Deduplication\DuplicateMatchStage;
use App\Domain\Document\Deduplication\DuplicateOutcome;
use App\Domain\Document\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Behaviour of {@see Document::recordDuplicate()} — the FIFO-bounded
 * `duplicates` collection used to trace tentative collects matched against
 * an already-indexed document (TAR-1145, ADR-2026-006 axe A).
 */
#[CoversClass(Document::class)]
class DocumentDuplicateTrackingTest extends TestCase
{
    private Document $document;

    protected function setUp(): void
    {
        $this->document = new Document(
            id: '00000000-0000-0000-0000-000000000001',
            title: 'Original article',
            excerpt: 'Excerpt',
            type: 'article',
            datePublish: new \DateTimeImmutable('2026-04-01'),
            dateCollect: new \DateTimeImmutable('2026-04-01'),
            content: 'Body of the original article',
        );
    }

    public function testNewlyCreatedDocumentHasNoDuplicates(): void
    {
        self::assertSame([], $this->document->getDuplicates());
    }

    public function testRecordSingleAttempt(): void
    {
        $attempt = $this->makeAttempt(DuplicateMatchStage::CANONICAL_URL);

        $this->document->recordDuplicate($attempt);

        self::assertSame([$attempt], $this->document->getDuplicates());
    }

    public function testRecordsAreReturnedNewestFirst(): void
    {
        $first = $this->makeAttempt(DuplicateMatchStage::CANONICAL_URL, suffix: 'first');
        $second = $this->makeAttempt(DuplicateMatchStage::CONTENT_HASH, suffix: 'second');
        $third = $this->makeAttempt(DuplicateMatchStage::SIM_HASH, suffix: 'third');

        $this->document
            ->recordDuplicate($first)
            ->recordDuplicate($second)
            ->recordDuplicate($third);

        $duplicates = $this->document->getDuplicates();
        self::assertSame([$third, $second, $first], $duplicates);
    }

    public function testFifoBoundedAtMaxDuplicateAttempts(): void
    {
        // Fill exactly to the cap.
        for ($i = 0; $i < Document::MAX_DUPLICATE_ATTEMPTS; ++$i) {
            $this->document->recordDuplicate(
                $this->makeAttempt(DuplicateMatchStage::CANONICAL_URL, suffix: "kept-{$i}")
            );
        }

        self::assertCount(Document::MAX_DUPLICATE_ATTEMPTS, $this->document->getDuplicates());

        // Adding one more must evict the oldest entry (at the tail), not throw.
        $newest = $this->makeAttempt(DuplicateMatchStage::CANONICAL_URL, suffix: 'overflow-1');
        $this->document->recordDuplicate($newest);

        $duplicates = $this->document->getDuplicates();
        self::assertCount(Document::MAX_DUPLICATE_ATTEMPTS, $duplicates);
        // Newest goes to the front.
        self::assertSame($newest, $duplicates[0]);
        // The first inserted entry (kept-0) was evicted from the tail;
        // kept-1 is now the oldest still alive, sitting at the tail.
        self::assertSame('https://example.com/kept-1', $duplicates[Document::MAX_DUPLICATE_ATTEMPTS - 1]->url);
    }

    public function testFifoEvictsMultipleEntriesWhenManyAreAddedPastTheCap(): void
    {
        // Push 60 entries on a 50-cap → 10 oldest must be evicted.
        for ($i = 0; $i < Document::MAX_DUPLICATE_ATTEMPTS + 10; ++$i) {
            $this->document->recordDuplicate(
                $this->makeAttempt(DuplicateMatchStage::CANONICAL_URL, suffix: "entry-{$i}"),
            );
        }

        $duplicates = $this->document->getDuplicates();
        self::assertCount(Document::MAX_DUPLICATE_ATTEMPTS, $duplicates);
        // The last inserted (entry-(MAX+9)) is the newest → index 0.
        self::assertSame(
            'https://example.com/entry-' . (Document::MAX_DUPLICATE_ATTEMPTS + 9),
            $duplicates[0]->url,
        );
        // entry-0..entry-9 evicted; entry-10 is the oldest still alive → at the tail.
        self::assertSame('https://example.com/entry-10', $duplicates[Document::MAX_DUPLICATE_ATTEMPTS - 1]->url);
    }

    public function testReturnsSelfForFluentChaining(): void
    {
        $result = $this->document->recordDuplicate($this->makeAttempt(DuplicateMatchStage::CANONICAL_URL));

        self::assertSame($this->document, $result);
    }

    public function testRerecordingSameUrlReplacesExistingEntryWithoutGrowingTheList(): void
    {
        // Same URL collected twice (e.g. cron retry, multi-source scrape of
        // the same article) must not bloat the bounded list — the existing
        // entry is refreshed with the new attempt's metadata.
        $first = $this->makeAttempt(DuplicateMatchStage::CANONICAL_URL, suffix: 'recurring');
        $secondSameUrl = new DuplicateAttempt(
            url: $first->url,
            watchFileId: 'wf-2',                        // different watchfile
            collectTaskId: 'ct-2',                      // different collect task
            sourceId: 'src-2',
            provider: 'bakus',                          // different provider
            collectedAt: new \DateTimeImmutable('+1 hour'),
            outcome: DuplicateOutcome::NEAR_EXACT,      // outcome upgraded
            matchStage: DuplicateMatchStage::SIM_HASH,
        );

        $this->document
            ->recordDuplicate($first)
            ->recordDuplicate($secondSameUrl);

        $duplicates = $this->document->getDuplicates();
        self::assertCount(1, $duplicates);
        self::assertSame($secondSameUrl, $duplicates[0]);
    }

    public function testRerecordingSameUrlBubblesRefreshedEntryToTheFront(): void
    {
        $a = $this->makeAttempt(DuplicateMatchStage::CANONICAL_URL, suffix: 'a');
        $b = $this->makeAttempt(DuplicateMatchStage::CANONICAL_URL, suffix: 'b');
        $c = $this->makeAttempt(DuplicateMatchStage::CANONICAL_URL, suffix: 'c');

        // After three appends with newest-first storage: [c, b, a]
        $this->document->recordDuplicate($a)
            ->recordDuplicate($b)
            ->recordDuplicate($c);

        // Rerecording `b` removes its old position and prepends the refreshed
        // entry — "seen again" is more relevant than "where it sat before".
        $bRefreshed = new DuplicateAttempt(
            url: $b->url,
            watchFileId: 'wf-refreshed',
            collectTaskId: 'ct-refreshed',
            sourceId: null,
            provider: 'manual',
            collectedAt: new \DateTimeImmutable('+1 day'),
            outcome: DuplicateOutcome::NEAR_DUPLICATE,
            matchStage: DuplicateMatchStage::MIN_HASH_LSH,
        );
        $this->document->recordDuplicate($bRefreshed);

        $duplicates = $this->document->getDuplicates();
        self::assertSame([$bRefreshed, $c, $a], $duplicates);
    }

    public function testNullUrlAttemptsAreAlwaysAppendedAndNeverDeduplicated(): void
    {
        // Raw-HTML pastes (CLI --html-file, API `html` payload) carry no URL.
        // Each paste is a distinct event with no idempotent key — record both,
        // newest-first, and rely on the FIFO cap to bound growth.
        $first = $this->makeNullUrlAttempt(suffix: 'paste-1');
        $second = $this->makeNullUrlAttempt(suffix: 'paste-2');

        $this->document
            ->recordDuplicate($first)
            ->recordDuplicate($second);

        $duplicates = $this->document->getDuplicates();
        self::assertCount(2, $duplicates);
        self::assertSame([$second, $first], $duplicates);
    }

    public function testFingerprintAccessorsRoundTripNullByDefault(): void
    {
        self::assertNull($this->document->getFingerprint());
    }

    private function makeAttempt(DuplicateMatchStage $stage, string $suffix = 'attempt'): DuplicateAttempt
    {
        return new DuplicateAttempt(
            url: "https://example.com/{$suffix}",
            watchFileId: 'wf-1',
            collectTaskId: 'ct-1',
            sourceId: 'src-1',
            provider: 'apify',
            collectedAt: new \DateTimeImmutable(),
            outcome: DuplicateOutcome::DUPLICATE,
            matchStage: $stage,
        );
    }

    private function makeNullUrlAttempt(string $suffix): DuplicateAttempt
    {
        return new DuplicateAttempt(
            url: null,
            watchFileId: 'wf-1',
            collectTaskId: "ct-{$suffix}",
            sourceId: 'src-1',
            provider: 'manual',
            collectedAt: new \DateTimeImmutable(),
            outcome: DuplicateOutcome::DUPLICATE,
            matchStage: DuplicateMatchStage::CONTENT_HASH,
        );
    }
}
