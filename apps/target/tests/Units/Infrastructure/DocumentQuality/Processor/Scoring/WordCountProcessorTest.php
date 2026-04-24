<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\DocumentQuality\Processor\Scoring;

use App\Domain\Document\Document;
use App\Domain\DocumentQuality\ProcessingContext;
use App\Domain\DocumentQuality\QualityDecision;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\DocumentQuality\Processor\Scoring\WordCountProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(WordCountProcessor::class)]
class WordCountProcessorTest extends TestCase
{
    private WordCountProcessor $processor;

    /** @var Document&\PHPUnit\Framework\MockObject\Stub */
    private Document $document;

    /** @var WatchFile&\PHPUnit\Framework\MockObject\Stub */
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->processor = new WordCountProcessor();
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
        $this->document->method('hasContent')
->willReturn(true);
        $context = new ProcessingContext($this->document, $this->watchFile);

        self::assertTrue($this->processor->supports($context));
    }

    public function testSupportsReturnsFalseWhenContentNotInitialized(): void
    {
        $this->document->method('hasContent')
->willReturn(false);
        $context = new ProcessingContext($this->document, $this->watchFile);

        self::assertFalse($this->processor->supports($context));
    }

    public function testSignalHasCorrectCategoryAndWeight(): void
    {
        $this->document->method('getWordCount')
->willReturn(500);
        $this->document->method('getUrl')
->willReturn('https://example.com/article');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $signal = $result->signals['word_count'];
        self::assertSame(SignalCategory::CONTENT_QUALITY, $signal->category);
        self::assertSame(0.6, $signal->weight);
    }

    // --- Standard content ---

    #[DataProvider('standardContentProvider')]
    public function testStandardContentScoring(int $wordCount, float $expectedScore): void
    {
        $this->document->method('getWordCount')
->willReturn($wordCount);
        $this->document->method('getUrl')
->willReturn('https://reuters.com/article');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertArrayHasKey('word_count', $result->signals);
        self::assertSame($expectedScore, $result->signals['word_count']->value);
    }

    /**
     * @return array<string, array{int, float}>
     */
    public static function standardContentProvider(): array
    {
        return [
            'below min (30 words) → 0.20' => [30, 0.20],
            'exactly min (50 words) → 0.60' => [50, 0.60],
            'short content (100 words) → 0.60' => [100, 0.60],
            'just below transition (199 words) → 0.60' => [199, 0.60],
            'at transition (200 words) → 0.80' => [200, 0.80],
            'mid range (500 words) → 0.80' => [500, 0.80],
            'at long threshold (1000 words) → 0.80' => [1000, 0.80],
            'long content (2000 words) → 0.90' => [2000, 0.90],
            'very long (15000 words) → 0.90' => [15000, 0.90],
        ];
    }

    // --- Social media ---

    #[DataProvider('socialMediaUrlProvider')]
    public function testSocialMediaDetectedByDomain(string $url): void
    {
        $this->document->method('getWordCount')
->willReturn(100);
        $this->document->method('getUrl')
->willReturn($url);
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        // 100 words on social media → > 50 → 0.80
        self::assertSame(0.80, $result->signals['word_count']->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function socialMediaUrlProvider(): array
    {
        return [
            'twitter.com' => ['https://twitter.com/status/123'],
            'x.com' => ['https://x.com/status/456'],
            'linkedin.com' => ['https://linkedin.com/posts/foo-bar'],
            'facebook.com' => ['https://facebook.com/post/789'],
            'instagram.com' => ['https://instagram.com/p/abc'],
            'threads.net' => ['https://threads.net/@user/post'],
            'mastodon.social' => ['https://mastodon.social/@user/123'],
            'bsky.app' => ['https://bsky.app/profile/user/post/123'],
            'reddit.com' => ['https://reddit.com/r/php/comments/abc'],
            't.me' => ['https://t.me/channel/123'],
            'subdomain x.com' => ['https://mobile.x.com/status/789'],
            'www prefix stripped' => ['https://www.twitter.com/status/123'],
            'IDN unicode domain (linkedin)' => ['https://linkedin.com/posts/foo'],
        ];
    }

    #[DataProvider('socialMediaScoringProvider')]
    public function testSocialMediaScoring(int $wordCount, float $expectedScore): void
    {
        $this->document->method('getWordCount')
->willReturn($wordCount);
        $this->document->method('getUrl')
->willReturn('https://x.com/status/123');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertArrayHasKey('word_count', $result->signals);
        self::assertSame($expectedScore, $result->signals['word_count']->value);
    }

    /**
     * @return array<string, array{int, float}>
     */
    public static function socialMediaScoringProvider(): array
    {
        return [
            'too short (2 words) → 0.30' => [2, 0.30],
            'below social min (9 words) → 0.30' => [9, 0.30],
            'at social min (10 words) → 0.60' => [10, 0.60],
            'short post (30 words) → 0.60' => [30, 0.60],
            'at transition (50 words) → 0.60' => [50, 0.60],
            'normal tweet (51 words) → 0.80' => [51, 0.80],
            'linkedin post (200 words) → 0.80' => [200, 0.80],
            'long thread (1000 words) → 0.80' => [1000, 0.80],
        ];
    }

    public function testNonSocialMediaUrlNotFlaggedAsSocial(): void
    {
        // 20 words on a non-social URL → standard scoring → 0.20 (below 50 min)
        $this->document->method('getWordCount')
->willReturn(20);
        $this->document->method('getUrl')
->willReturn('https://lemonde.fr/article');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.20, $result->signals['word_count']->value);
    }

    public function testNullUrlFallsBackToStandardScoring(): void
    {
        $this->document->method('getWordCount')
->willReturn(500);
        $this->document->method('getUrl')
->willReturn(null);
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.80, $result->signals['word_count']->value);
    }

    public function testReasonContainsWordCount(): void
    {
        $this->document->method('getWordCount')
->willReturn(1000);
        $this->document->method('getUrl')
->willReturn('https://example.com/article');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $reason = $result->signals['word_count']->reason;
        self::assertNotNull($reason);
        self::assertStringContainsString('1000', $reason->en);
        self::assertStringContainsString('1000', $reason->fr);
    }
}
