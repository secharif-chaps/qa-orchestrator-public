<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document\Pipeline\Processor\Scoring;

use App\Domain\Document\Document;
use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Document\Pipeline\Processor\Scoring\TldReputationProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(TldReputationProcessor::class)]
class TldReputationProcessorTest extends TestCase
{
    private TldReputationProcessor $processor;

    /** @var Document&Stub */
    private Document $document;

    /** @var WatchFile&Stub */
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->processor = new TldReputationProcessor();
        $this->document = $this->createStub(Document::class);
        $this->watchFile = $this->createStub(WatchFile::class);
    }

    public function testSupportsReturnsFalseWhenHalted(): void
    {
        $context = new DocumentPipelineContext(
            document: $this->document,
            watchFile: $this->watchFile,
            isHalted: true,
            haltReason: new TranslatedText('halté', 'halted'),
        );

        self::assertFalse($this->processor->supports($context));
    }

    public function testSupportsReturnsFalseWhenUrlIsNull(): void
    {
        $this->document->method('getUrl')
->willReturn(null);
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        self::assertFalse($this->processor->supports($context));
    }

    public function testSignalHasCorrectCategoryAndWeight(): void
    {
        $this->document->method('getUrl')
->willReturn('https://example.com');
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $signal = $result->signals['tld_reputation'];
        self::assertSame(SignalCategory::INFRASTRUCTURE_TRUST, $signal->category);
        self::assertSame(1.0, $signal->weight);
    }

    #[DataProvider('tldScoringProvider')]
    public function testTldScoring(string $url, float $expectedScore): void
    {
        $this->document->method('getUrl')
->willReturn($url);
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertArrayHasKey('tld_reputation', $result->signals);
        self::assertSame($expectedScore, $result->signals['tld_reputation']->value);
    }

    /**
     * @return array<string, array{0: string, 1: float}>
     */
    public static function tldScoringProvider(): array
    {
        return [
            // Suspicious
            'tk → 0.15 (suspicious)' => ['https://example.tk', 0.15],
            'ml → 0.20 (suspicious)' => ['https://example.ml', 0.20],
            'xyz → 0.35 (suspicious)' => ['https://example.xyz', 0.35],
            // Trusted (single label)
            'gov → 0.95 (trusted)' => ['https://whitehouse.gov', 0.95],
            'edu → 0.90 (trusted)' => ['https://mit.edu', 0.90],
            // Trusted (multi-label)
            'gov.uk → 0.95 (trusted multi-label)' => ['https://service.gov.uk', 0.95],
            'gouv.fr → 0.95 (trusted multi-label)' => ['https://service-public.gouv.fr', 0.95],
            'edu.au → 0.90 (trusted multi-label)' => ['https://monash.edu.au', 0.90],
            // Neutral
            'com → 0.60 (neutral)' => ['https://example.com', 0.60],
            'fr → 0.60 (neutral)' => ['https://lemonde.fr', 0.60],
            'de → 0.60 (neutral)' => ['https://spiegel.de', 0.60],
            // Sub-sub-domains do not change TLD
            'subdomain → uses TLD only' => ['https://a.b.c.example.com', 0.60],
        ];
    }

    public function testUnparsableUrlReturnsLowConfidenceScore(): void
    {
        $this->document->method('getUrl')
->willReturn('not-a-url');
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $signal = $result->signals['tld_reputation'];
        self::assertSame(0.30, $signal->value);
        self::assertNotNull($signal->reason);
        self::assertStringContainsString('parse', strtolower($signal->reason->en));
    }

    public function testReasonsAreTranslated(): void
    {
        $this->document->method('getUrl')
->willReturn('https://example.tk');
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $reason = $result->signals['tld_reputation']->reason;
        self::assertNotNull($reason);
        self::assertStringContainsStringIgnoringCase('tk', $reason->fr);
        self::assertStringContainsStringIgnoringCase('tk', $reason->en);
        self::assertStringContainsStringIgnoringCase('spam', $reason->en);
    }

    public function testMultiLabelTldTakesPrecedenceOverSingleLabel(): void
    {
        $this->document->method('getUrl')
->willReturn('https://example.gov.uk');
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        // gov.uk is trusted (0.95), uk alone would be neutral (0.60)
        self::assertSame(0.95, $result->signals['tld_reputation']->value);
    }
}
