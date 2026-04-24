<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\DocumentQuality\Processor\Scoring;

use App\Domain\Document\Document;
use App\Domain\DocumentQuality\ProcessingContext;
use App\Domain\DocumentQuality\QualityDecision;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\DocumentQuality\Processor\Scoring\UrlPatternProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UrlPatternProcessor::class)]
class UrlPatternProcessorTest extends TestCase
{
    private UrlPatternProcessor $processor;

    /** @var Document&\PHPUnit\Framework\MockObject\Stub */
    private Document $document;

    /** @var WatchFile&\PHPUnit\Framework\MockObject\Stub */
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->processor = new UrlPatternProcessor();
        $this->document = $this->createStub(Document::class);
        $this->watchFile = $this->createStub(WatchFile::class);
    }

    public function testSupportsReturnsFalseWhenEarlyDecisionIsSet(): void
    {
        $this->document->method('getUrl')
->willReturn('https://example.com/');
        $context = new ProcessingContext(
            document: $this->document,
            watchFile: $this->watchFile,
            earlyDecision: QualityDecision::REJECTED,
            earlyDecisionReason: 'blocked',
        );

        self::assertFalse($this->processor->supports($context));
    }

    public function testSupportsReturnsFalseWhenUrlIsNull(): void
    {
        $this->document->method('getUrl')
->willReturn(null);
        $context = new ProcessingContext($this->document, $this->watchFile);

        self::assertFalse($this->processor->supports($context));
    }

    public function testSupportsReturnsTrueWhenUrlIsPresent(): void
    {
        $this->document->method('getUrl')
->willReturn('https://example.com/article');
        $context = new ProcessingContext($this->document, $this->watchFile);

        self::assertTrue($this->processor->supports($context));
    }

    public function testSignalHasCorrectCategoryAndWeight(): void
    {
        $this->document->method('getUrl')
->willReturn('https://reuters.com/2024/article');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $signal = $result->signals['url_pattern'];
        self::assertSame(SignalCategory::CONTENT_QUALITY, $signal->category);
        self::assertSame(0.9, $signal->weight);
    }

    public function testRootUrlEmitsDefaultScore060(): void
    {
        $this->document->method('getUrl')
->willReturn('https://example.com');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.70, $result->signals['url_pattern']->value);
    }

    public function testRootUrlWithTrailingSlashEmitsDefaultScore060(): void
    {
        $this->document->method('getUrl')
->willReturn('https://example.com/');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.70, $result->signals['url_pattern']->value);
    }

    public function testStandardUrlEmitsDefaultScore060(): void
    {
        $this->document->method('getUrl')
->willReturn('https://reuters.com/2024/01/air-france-results');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.70, $result->signals['url_pattern']->value);
    }

    public function testDeepUrlEmitsScore040(): void
    {
        // 7 levels deep: a/b/c/d/e/f/page → depth 6 > MAX_URL_DEPTH(5) → triggers
        $this->document->method('getUrl')
->willReturn('https://example.com/a/b/c/d/e/f/page');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.40, $result->signals['url_pattern']->value);
    }

    public function testUrlAtDepth4DoesNotTriggerDeepPattern(): void
    {
        // 4 slashes in path → depth 4, below threshold of 5
        $this->document->method('getUrl')
->willReturn('https://example.com/a/b/c/d/company-profile');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.70, $result->signals['url_pattern']->value);
    }

    public function testKeywordStuffingEmitsScore035(): void
    {
        $this->document->method('getUrl')
->willReturn('https://seo.com/best-cheap-fast-amazing-deals-now');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.35, $result->signals['url_pattern']->value);
    }

    public function testSlugWithFiveDashSegmentsDoesNotTriggerStuffing(): void
    {
        // 5 segments = exactly below threshold of 6
        $this->document->method('getUrl')
->willReturn('https://site.com/word-one-two-three-four');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.70, $result->signals['url_pattern']->value);
    }

    public function testNegativePatternTakesPriorityOverDeepUrl(): void
    {
        // login pattern should match before depth check
        $this->document->method('getUrl')
->willReturn('https://example.com/a/b/c/d/e/login');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.10, $result->signals['url_pattern']->value);
    }

    #[DataProvider('negativePatternProvider')]
    public function testNegativePatternEmitsExpectedScore(string $url, float $expectedScore): void
    {
        $this->document->method('getUrl')
->willReturn($url);
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertArrayHasKey('url_pattern', $result->signals);
        self::assertSame($expectedScore, $result->signals['url_pattern']->value);
    }

    /**
     * @return array<string, array{string, float}>
     */
    public static function negativePatternProvider(): array
    {
        return [
            'login page' => ['https://acme.com/login', 0.10],
            'signin page' => ['https://acme.com/signin', 0.10],
            'auth page' => ['https://acme.com/auth/callback', 0.10],
            'logout page' => ['https://acme.com/logout', 0.10],
            'register page' => ['https://acme.com/register', 0.10],
            'cart page' => ['https://shop.fr/cart', 0.15],
            'checkout page' => ['https://shop.fr/checkout', 0.15],
            'basket page' => ['https://shop.fr/basket', 0.15],
            'payment page' => ['https://shop.fr/payment', 0.15],
            'privacy page' => ['https://site.com/privacy', 0.25],
            'terms page' => ['https://site.com/terms', 0.25],
            'legal page' => ['https://site.com/legal', 0.25],
            'cookie page' => ['https://site.com/cookie-policy', 0.25],
            'gdpr page' => ['https://site.com/gdpr', 0.25],
            'cgu page' => ['https://site.com/cgu', 0.25],
            'cgv page' => ['https://site.com/cgv', 0.25],
            'search page' => ['https://lemonde.fr/search?q=france', 0.20],
            'recherche page' => ['https://site.fr/recherche', 0.20],
            'pagination page' => ['https://blog.com/page/3', 0.25],
            'category listing' => ['https://blog.com/category/tech', 0.30],
            'tag listing' => ['https://blog.com/tag/php', 0.30],
            'archive listing' => ['https://blog.com/archive/2024', 0.30],
            'contact page' => ['https://acme.com/contact', 0.40],
            'about page' => ['https://acme.com/about', 0.40],
            'a-propos page' => ['https://acme.fr/a-propos', 0.40],
            'qui-sommes-nous page' => ['https://acme.fr/qui-sommes-nous', 0.40],
        ];
    }

    #[DataProvider('positivePatternProvider')]
    public function testPositivePatternEmitsExpectedScore(string $url, float $expectedScore): void
    {
        $this->document->method('getUrl')
->willReturn($url);
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertArrayHasKey('url_pattern', $result->signals);
        self::assertSame($expectedScore, $result->signals['url_pattern']->value);
    }

    /**
     * @return array<string, array{string, float}>
     */
    public static function positivePatternProvider(): array
    {
        return [
            'article page' => ['https://lemonde.fr/article/2024/air-france', 0.85],
            'articles listing' => ['https://lemonde.fr/articles/tech', 0.85],
            'news page' => ['https://reuters.com/news/finance', 0.85],
            'blog post' => ['https://example.com/blog/symfony-tips', 0.80],
            'billet page' => ['https://site.fr/billet/mon-billet', 0.80],
            'press page' => ['https://acme.com/press/2024-results', 0.75],
            'presse page' => ['https://acme.fr/presse/communique', 0.75],
            'communique page' => ['https://acme.fr/communique/fusion', 0.75],
            'press-release page' => ['https://acme.com/press-release/q4', 0.75],
        ];
    }

    public function testPositivePatternTakesPriorityOverNegativePattern(): void
    {
        // /news/ in path dominates incidental /login segment: editorial intent wins
        $this->document->method('getUrl')
->willReturn('https://example.com/news/login-security-tips');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.85, $result->signals['url_pattern']->value);
    }

    public function testPositivePatternTakesPriorityOverDeepUrl(): void
    {
        // /article in deep URL: editorial marker wins over depth penalty
        $this->document->method('getUrl')
->willReturn('https://example.com/a/b/c/d/e/f/article');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.85, $result->signals['url_pattern']->value);
    }

    public function testNegativePatternStillAppliesWhenNoPositiveMatch(): void
    {
        // Pure negative URL with no editorial marker → negative wins
        $this->document->method('getUrl')
->willReturn('https://example.com/login');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.10, $result->signals['url_pattern']->value);
    }

    public function testEditorialArticleAboutGdprGetsPositiveScore(): void
    {
        // /articles/ path with gdpr keyword → positive wins (it's an article about GDPR)
        $this->document->method('getUrl')
->willReturn('https://lemonde.fr/articles/gdpr-compliance-guide');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.85, $result->signals['url_pattern']->value);
    }

    public function testReasonContainsMeaningfulText(): void
    {
        $this->document->method('getUrl')
->willReturn('https://acme.com/login');
        $context = new ProcessingContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $reason = $result->signals['url_pattern']->reason;
        self::assertNotNull($reason);
        self::assertNotEmpty($reason->en);
        self::assertNotEmpty($reason->fr);
    }
}
