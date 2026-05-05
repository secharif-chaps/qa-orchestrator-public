<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Deduplication;

use App\Domain\Document\Deduplication\DuplicateAttempt;
use App\Domain\Document\Deduplication\DuplicateMatchStage;
use App\Domain\Document\Deduplication\DuplicateOutcome;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DuplicateAttempt::class)]
class DuplicateAttemptTest extends TestCase
{
    public function testValidConstruction(): void
    {
        $now = new \DateTimeImmutable();
        $attempt = new DuplicateAttempt(
            url: 'https://example.com/article',
            watchFileId: 'wf-1',
            collectTaskId: 'ct-1',
            sourceId: 'src-1',
            provider: 'apify',
            collectedAt: $now,
            outcome: DuplicateOutcome::DUPLICATE,
            matchStage: DuplicateMatchStage::CANONICAL_URL,
            similarity: 1.0,
        );

        self::assertSame('https://example.com/article', $attempt->url);
        self::assertSame('wf-1', $attempt->watchFileId);
        self::assertSame('ct-1', $attempt->collectTaskId);
        self::assertSame('src-1', $attempt->sourceId);
        self::assertSame('apify', $attempt->provider);
        self::assertSame($now, $attempt->collectedAt);
        self::assertSame(DuplicateOutcome::DUPLICATE, $attempt->outcome);
        self::assertSame(DuplicateMatchStage::CANONICAL_URL, $attempt->matchStage);
        self::assertSame(1.0, $attempt->similarity);
    }

    public function testSourceIdAndSimilarityAreOptional(): void
    {
        $attempt = new DuplicateAttempt(
            url: 'https://example.com/article',
            watchFileId: 'wf-1',
            collectTaskId: 'ct-1',
            sourceId: null,
            provider: 'manual',
            collectedAt: new \DateTimeImmutable(),
            outcome: DuplicateOutcome::DUPLICATE,
            matchStage: DuplicateMatchStage::CONTENT_HASH,
        );

        self::assertNull($attempt->sourceId);
        self::assertNull($attempt->similarity);
    }

    public function testRejectsUniqueOutcome(): void
    {
        // UNIQUE is the "no match" verdict — there is no original to attach
        // a duplicate attempt to, so building one with this outcome would
        // be a programming error.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot record the UNIQUE outcome');

        new DuplicateAttempt(
            url: 'https://example.com/article',
            watchFileId: 'wf',
            collectTaskId: 'ct',
            sourceId: null,
            provider: 'apify',
            collectedAt: new \DateTimeImmutable(),
            outcome: DuplicateOutcome::UNIQUE,
            matchStage: DuplicateMatchStage::CANONICAL_URL,
        );
    }

    public function testAcceptsAllNonUniqueOutcomes(): void
    {
        foreach ([
            DuplicateOutcome::DUPLICATE,
            DuplicateOutcome::NEAR_EXACT,
            DuplicateOutcome::NEAR_DUPLICATE,
        ] as $outcome) {
            $attempt = new DuplicateAttempt(
                url: 'https://example.com/article',
                watchFileId: 'wf',
                collectTaskId: 'ct',
                sourceId: null,
                provider: 'apify',
                collectedAt: new \DateTimeImmutable(),
                outcome: $outcome,
                matchStage: DuplicateMatchStage::SIM_HASH,
            );

            self::assertSame($outcome, $attempt->outcome);
        }
    }

    public function testAcceptsAllMatchStages(): void
    {
        foreach (DuplicateMatchStage::cases() as $stage) {
            $attempt = new DuplicateAttempt(
                url: 'https://example.com/article',
                watchFileId: 'wf',
                collectTaskId: 'ct',
                sourceId: null,
                provider: 'apify',
                collectedAt: new \DateTimeImmutable(),
                outcome: DuplicateOutcome::DUPLICATE,
                matchStage: $stage,
            );

            self::assertSame($stage, $attempt->matchStage);
        }
    }
}
