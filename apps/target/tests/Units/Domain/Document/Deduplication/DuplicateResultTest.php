<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Deduplication;

use App\Domain\Document\Deduplication\DuplicateMatchStage;
use App\Domain\Document\Deduplication\DuplicateOutcome;
use App\Domain\Document\Deduplication\DuplicateResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DuplicateResult::class)]
class DuplicateResultTest extends TestCase
{
    public function testUniqueFactoryProducesAUniqueOutcomeWithoutMatchData(): void
    {
        $result = DuplicateResult::unique();

        self::assertSame(DuplicateOutcome::UNIQUE, $result->outcome);
        self::assertNull($result->stage);
        self::assertNull($result->originalDocumentId);
        self::assertNull($result->similarity);
        self::assertFalse($result->isMatch());
    }

    public function testMatchFactoryBuildsFullyPopulatedResult(): void
    {
        $result = DuplicateResult::match(
            outcome: DuplicateOutcome::DUPLICATE,
            stage: DuplicateMatchStage::CANONICAL_URL,
            originalDocumentId: 'doc-123',
            similarity: 1.0,
        );

        self::assertTrue($result->isMatch());
        self::assertSame(DuplicateOutcome::DUPLICATE, $result->outcome);
        self::assertSame(DuplicateMatchStage::CANONICAL_URL, $result->stage);
        self::assertSame('doc-123', $result->originalDocumentId);
        self::assertSame(1.0, $result->similarity);
    }

    public function testMatchFactoryRejectsTheUniqueOutcome(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DuplicateResult::match(
            outcome: DuplicateOutcome::UNIQUE,
            stage: DuplicateMatchStage::CANONICAL_URL,
            originalDocumentId: 'doc-1',
            similarity: 1.0,
        );
    }
}
