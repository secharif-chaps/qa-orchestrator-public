<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\DocumentQuality\Processor\Scoring;

use App\Domain\Document\Document;
use App\Domain\DocumentQuality\ProcessingContext;
use App\Domain\DocumentQuality\QualityDecision;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\DocumentQuality\Processor\Scoring\ContentRatioProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentRatioProcessor::class)]
class ContentRatioProcessorTest extends TestCase
{
    private ContentRatioProcessor $processor;

    /** @var Document&\PHPUnit\Framework\MockObject\Stub */
    private Document $document;

    /** @var WatchFile&\PHPUnit\Framework\MockObject\Stub */
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->processor = new ContentRatioProcessor();
        $this->document = $this->createStub(Document::class);
        $this->watchFile = $this->createStub(WatchFile::class);
    }

    public function testSupportsReturnsFalseWhenEarlyDecisionIsSet(): void
    {
        $context = new ProcessingContext(
            document: $this->document,
            watchFile: $this->watchFile,
            earlyDecision: QualityDecision::REJECTED,
            earlyDecisionReason: 'blocked',
        );

        self::assertFalse($this->processor->supports($context));
    }

    public function testSupportsReturnsTrueForHtmlDocumentWithNoEarlyDecision(): void
    {
        $this->document->method('getType')
->willReturn('html');
        $context = new ProcessingContext($this->document, $this->watchFile);

        self::assertTrue($this->processor->supports($context));
    }

    public function testSupportsReturnsFalseForNonHtmlDocument(): void
    {
        $this->document->method('getType')
->willReturn('rss');
        $context = new ProcessingContext($this->document, $this->watchFile);

        self::assertFalse($this->processor->supports($context));
    }

    public function testSignalHasCorrectCategoryAndWeight(): void
    {
        $this->document->method('getContentRatio')
->willReturn(0.40);
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $signal = $result->signals['content_ratio'];
        self::assertSame(SignalCategory::CONTENT_QUALITY, $signal->category);
        self::assertSame(0.7, $signal->weight);
    }

    public function testNullRatioEmitsNeutralScore050(): void
    {
        $this->document->method('getContentRatio')
->willReturn(null);
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertArrayHasKey('content_ratio', $result->signals);
        self::assertSame(0.50, $result->signals['content_ratio']->value);
    }

    public function testNullRatioReasonMentionsUnavailable(): void
    {
        $this->document->method('getContentRatio')
->willReturn(null);
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $reason = $result->signals['content_ratio']->reason;
        self::assertNotNull($reason);
        self::assertStringContainsStringIgnoringCase('not available', $reason->en);
    }

    #[DataProvider('ratioScoringProvider')]
    public function testRatioScoring(float $ratio, float $expectedScore): void
    {
        $this->document->method('getContentRatio')
->willReturn($ratio);
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertArrayHasKey('content_ratio', $result->signals);
        self::assertSame($expectedScore, $result->signals['content_ratio']->value);
    }

    /**
     * @return array<string, array{float, float}>
     */
    public static function ratioScoringProvider(): array
    {
        return [
            '5% → 0.20 (ad-heavy)' => [0.05, 0.20],
            'just below 10% → 0.20' => [0.099, 0.20],
            '10% boundary → 0.55' => [0.10, 0.55],
            '20% → 0.55' => [0.20, 0.55],
            '29% → 0.55' => [0.29, 0.55],
            '30% boundary → 0.80' => [0.30, 0.80],
            '50% → 0.80' => [0.50, 0.80],
            '90% → 0.80 (PDF converted)' => [0.90, 0.80],
        ];
    }

    public function testLowRatioReasonMentionsRatioPercentage(): void
    {
        $this->document->method('getContentRatio')
->willReturn(0.05);
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $reason = $result->signals['content_ratio']->reason;
        self::assertNotNull($reason);
        self::assertStringContainsString('5.0%', $reason->en);
        self::assertStringContainsString('5.0%', $reason->fr);
    }

    public function testGoodRatioReasonMentionsRatioPercentage(): void
    {
        $this->document->method('getContentRatio')
->willReturn(0.46);
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $reason = $result->signals['content_ratio']->reason;
        self::assertNotNull($reason);
        self::assertStringContainsString('46.0%', $reason->en);
        self::assertStringContainsString('46.0%', $reason->fr);
    }
}
