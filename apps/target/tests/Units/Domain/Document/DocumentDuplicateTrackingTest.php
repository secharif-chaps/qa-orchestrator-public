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

    public function testRecordsAreReturnedInInsertionOrder(): void
    {
        $first = $this->makeAttempt(DuplicateMatchStage::CANONICAL_URL, suffix: 'first');
        $second = $this->makeAttempt(DuplicateMatchStage::CONTENT_HASH, suffix: 'second');
        $third = $this->makeAttempt(DuplicateMatchStage::SIM_HASH, suffix: 'third');

        $this->document
            ->recordDuplicate($first)
            ->recordDuplicate($second)
            ->recordDuplicate($third);

        $duplicates = $this->document->getDuplicates();
        self::assertSame([$first, $second, $third], $duplicates);
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

        // Adding one more must evict the oldest entry, not throw.
        $newest = $this->makeAttempt(DuplicateMatchStage::CANONICAL_URL, suffix: 'overflow-1');
        $this->document->recordDuplicate($newest);

        $duplicates = $this->document->getDuplicates();
        self::assertCount(Document::MAX_DUPLICATE_ATTEMPTS, $duplicates);
        self::assertSame($newest, $duplicates[Document::MAX_DUPLICATE_ATTEMPTS - 1]);
        // The first entry (kept-0) was evicted; kept-1 is now at index 0.
        self::assertSame('https://example.com/kept-1', $duplicates[0]->url);
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
        self::assertSame('https://example.com/entry-10', $duplicates[0]->url);
        self::assertSame(
            'https://example.com/entry-' . (Document::MAX_DUPLICATE_ATTEMPTS + 9),
            $duplicates[Document::MAX_DUPLICATE_ATTEMPTS - 1]->url,
        );
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

    public function testRerecordingSameUrlPreservesPositionsOfOtherEntries(): void
    {
        $a = $this->makeAttempt(DuplicateMatchStage::CANONICAL_URL, suffix: 'a');
        $b = $this->makeAttempt(DuplicateMatchStage::CANONICAL_URL, suffix: 'b');
        $c = $this->makeAttempt(DuplicateMatchStage::CANONICAL_URL, suffix: 'c');

        // Initial order: [a, b, c]
        $this->document->recordDuplicate($a)
            ->recordDuplicate($b)
            ->recordDuplicate($c);

        // Rerecording `b` must overwrite at index 1, not move it to the tail.
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
        self::assertSame([$a, $bRefreshed, $c], $duplicates);
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
}
