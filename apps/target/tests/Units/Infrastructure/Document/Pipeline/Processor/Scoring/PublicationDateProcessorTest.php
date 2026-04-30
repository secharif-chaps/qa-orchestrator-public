<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document\Pipeline\Processor\Scoring;

use App\Domain\Document\Document;
use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Document\Pipeline\Processor\Scoring\PublicationDateProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PublicationDateProcessor::class)]
class PublicationDateProcessorTest extends TestCase
{
    private PublicationDateProcessor $processor;

    /** @var Document&\PHPUnit\Framework\MockObject\Stub */
    private Document $document;

    /** @var WatchFile&\PHPUnit\Framework\MockObject\Stub */
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->processor = new PublicationDateProcessor();
        $this->document = $this->createStub(Document::class);
        $this->watchFile = $this->createStub(WatchFile::class);
    }

    public function testSupportsReturnsFalseWhenEarlyDecisionIsSet(): void
    {
        $context = new DocumentPipelineContext(
            document: $this->document,
            watchFile: $this->watchFile,
            isHalted: true,
            haltReason: new TranslatedText('bloqué', 'blocked'),
        );

        self::assertFalse($this->processor->supports($context));
    }

    public function testSupportsReturnsTrueWhenDatePublishIsSet(): void
    {
        $this->document->method('hasDatePublish')
->willReturn(true);
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        self::assertTrue($this->processor->supports($context));
    }

    public function testSupportsReturnsFalseWhenDatePublishIsUninitialized(): void
    {
        $this->document->method('hasDatePublish')
->willReturn(false);
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        self::assertFalse($this->processor->supports($context));
    }

    public function testSignalHasCorrectCategoryAndWeight(): void
    {
        $this->document->method('getDatePublish')
->willReturn(new \DateTimeImmutable('-1 day'));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $signal = $result->signals['publication_date'];
        self::assertSame(SignalCategory::METADATA, $signal->category);
        self::assertSame(0.5, $signal->weight);
    }

    public function testFutureDateEmitsScore025(): void
    {
        $this->document->method('getDatePublish')
->willReturn(new \DateTimeImmutable('+1 day'));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertArrayHasKey('publication_date', $result->signals);
        self::assertSame(0.25, $result->signals['publication_date']->value);
    }

    public function testFarFutureDateEmitsScore025(): void
    {
        $this->document->method('getDatePublish')
->willReturn(new \DateTimeImmutable('+2 years'));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.25, $result->signals['publication_date']->value);
    }

    public function testOldContentBeyond5YearsEmitsScore050(): void
    {
        $this->document->method('getDatePublish')
->willReturn(new \DateTimeImmutable('-6 years'));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.50, $result->signals['publication_date']->value);
    }

    public function testContentBeyond3YearsIsOld(): void
    {
        // 1826 days > 1095 threshold → old
        $this->document->method('getDatePublish')
->willReturn(new \DateTimeImmutable('-1826 days'));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.50, $result->signals['publication_date']->value);
    }

    public function testContentBetween1And3YearsEmitsScore065(): void
    {
        // 2 years ago → 1-3 years bracket
        $this->document->method('getDatePublish')
->willReturn(new \DateTimeImmutable('-2 years'));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.65, $result->signals['publication_date']->value);
    }

    public function testContentJustOver3YearsEmitsScore050(): void
    {
        // 1096 days > 1095 threshold → old
        $this->document->method('getDatePublish')
->willReturn(new \DateTimeImmutable('-1096 days'));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.50, $result->signals['publication_date']->value);
    }

    public function testFreshArticleUnder7DaysEmitsScore085(): void
    {
        $this->document->method('getDatePublish')
->willReturn(new \DateTimeImmutable('-3 days'));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.85, $result->signals['publication_date']->value);
    }

    public function testTodayPublicationEmitsScore085(): void
    {
        $this->document->method('getDatePublish')
->willReturn(new \DateTimeImmutable('today'));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.85, $result->signals['publication_date']->value);
    }

    public function testArticleUnder1YearEmitsScore080(): void
    {
        $this->document->method('getDatePublish')
->willReturn(new \DateTimeImmutable('-6 months'));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.80, $result->signals['publication_date']->value);
    }

    public function testFutureReasonMentionsSuspicious(): void
    {
        $this->document->method('getDatePublish')
->willReturn(new \DateTimeImmutable('+1 month'));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $reason = $result->signals['publication_date']->reason;
        self::assertNotNull($reason);
        self::assertStringContainsStringIgnoringCase('future', $reason->en);
        self::assertStringContainsStringIgnoringCase('futur', $reason->fr);
    }

    public function testOldContentReasonMentionsYearsAgo(): void
    {
        $this->document->method('getDatePublish')
->willReturn(new \DateTimeImmutable('-7 years'));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $reason = $result->signals['publication_date']->reason;
        self::assertNotNull($reason);
        self::assertStringContainsString('7', $reason->en);
        self::assertStringContainsString('7', $reason->fr);
    }

    public function testRecentReasonContainsFormattedDate(): void
    {
        $date = new \DateTimeImmutable('2026-03-15');
        $this->document->method('getDatePublish')
->willReturn($date);
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $reason = $result->signals['publication_date']->reason;
        self::assertNotNull($reason);
        self::assertStringContainsString('2026-03-15', $reason->en);
        self::assertStringContainsString('2026-03-15', $reason->fr);
    }

    public function testOldContentReasonUsePluralForMultipleYears(): void
    {
        $this->document->method('getDatePublish')
->willReturn(new \DateTimeImmutable('-9 years'));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $reason = $result->signals['publication_date']->reason;
        self::assertNotNull($reason);
        self::assertStringContainsString('years', $reason->en);
        self::assertStringContainsString('ans', $reason->fr);
    }
}
