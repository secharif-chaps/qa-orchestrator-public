<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\DocumentQuality\Processor\Scoring;

use App\Domain\Document\Document;
use App\Domain\DocumentQuality\ProcessingContext;
use App\Domain\DocumentQuality\QualityDecision;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\DocumentQuality\Processor\Scoring\HttpsProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpsProcessor::class)]
class HttpsProcessorTest extends TestCase
{
    private HttpsProcessor $processor;

    /** @var Document&\PHPUnit\Framework\MockObject\Stub */
    private Document $document;

    /** @var WatchFile&\PHPUnit\Framework\MockObject\Stub */
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->processor = new HttpsProcessor();
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

    public function testSupportsReturnsTrueWhenNoEarlyDecision(): void
    {
        $this->document->method('getUrl')
->willReturn('https://example.com');
        $context = new ProcessingContext($this->document, $this->watchFile);

        self::assertTrue($this->processor->supports($context));
    }

    public function testSupportsReturnsFalseWhenUrlIsNull(): void
    {
        $this->document->method('getUrl')
->willReturn(null);
        $context = new ProcessingContext($this->document, $this->watchFile);

        self::assertFalse($this->processor->supports($context));
    }

    public function testSignalHasCorrectCategoryAndWeight(): void
    {
        $this->document->method('getUrl')
->willReturn('https://example.com');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $signal = $result->signals['https'];
        self::assertSame(SignalCategory::INFRASTRUCTURE_TRUST, $signal->category);
        self::assertSame(0.5, $signal->weight);
    }

    #[DataProvider('urlScoringProvider')]
    public function testUrlScoring(string $url, float $expectedScore): void
    {
        $this->document->method('getUrl')
->willReturn($url);
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertArrayHasKey('https', $result->signals);
        self::assertSame($expectedScore, $result->signals['https']->value);
    }

    /**
     * @return array<string, array{string, float}>
     */
    public static function urlScoringProvider(): array
    {
        return [
            'https URL → 0.80' => ['https://example.com/article', 0.80],
            'https with path → 0.80' => ['https://lemonde.fr/2024/article', 0.80],
            'http URL → 0.35' => ['http://example.com/article', 0.35],
            'http with path → 0.35' => ['http://old-site.com/page', 0.35],
        ];
    }

    public function testHttpsReasonMentionsHttps(): void
    {
        $this->document->method('getUrl')
->willReturn('https://example.com');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $reason = $result->signals['https']->reason;
        self::assertNotNull($reason);
        self::assertStringContainsStringIgnoringCase('https', $reason->en);
    }

    public function testHttpReasonMentionsHttp(): void
    {
        $this->document->method('getUrl')
->willReturn('http://example.com');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $reason = $result->signals['https']->reason;
        self::assertNotNull($reason);
        self::assertStringContainsStringIgnoringCase('http', $reason->en);
    }
}
