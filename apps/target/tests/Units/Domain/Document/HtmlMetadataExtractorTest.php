<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document;

use App\Domain\Document\HtmlMetadata;
use App\Domain\Document\HtmlMetadataExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HtmlMetadataExtractor::class)]
class HtmlMetadataExtractorTest extends TestCase
{
    private HtmlMetadataExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new HtmlMetadataExtractor();
    }

    public function testExtractTitleFromTitleTag(): void
    {
        $html = '<html><head><title>My Page Title</title></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('My Page Title', $metadata->title);
    }

    public function testExtractTitleFromOgTag(): void
    {
        $html = '<html><head><meta property="og:title" content="OG Title"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('OG Title', $metadata->title);
    }

    public function testExtractTitleFromH1(): void
    {
        $html = '<html><head></head><body><h1>H1 Title</h1><p>Content</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('H1 Title', $metadata->title);
    }

    public function testExtractTitleFallback(): void
    {
        $html = '<html><head></head><body><p>Just a paragraph</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame(HtmlMetadata::UNTITLED, $metadata->title);
    }

    public function testExtractExcerptFromMetaDescription(): void
    {
        $html = '<html><head><meta name="description" content="A great page description"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('A great page description', $metadata->excerpt);
    }

    public function testExtractExcerptFromOgDescription(): void
    {
        $html = '<html><head><meta property="og:description" content="OG Description"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('OG Description', $metadata->excerpt);
    }

    public function testExtractExcerptFallbackToContent(): void
    {
        $html = '<html><head></head><body><p>Some body text for the page that will be used as excerpt</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertStringStartsWith('Some body text', $metadata->excerpt);
        $this->assertLessThanOrEqual(200, mb_strlen($metadata->excerpt));
    }

    public function testExtractLanguageFromHtmlLang(): void
    {
        $html = '<html lang="en"><head></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('en', $metadata->language);
    }

    public function testExtractLanguageFromHtmlLangWithRegion(): void
    {
        $html = '<html lang="fr-FR"><head></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('fr', $metadata->language);
    }

    public function testExtractLanguageDefaultsToFr(): void
    {
        $html = '<html><head></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('fr', $metadata->language);
    }

    public function testExtractLanguageUnsupportedDefaultsToFr(): void
    {
        $html = '<html lang="de"><head></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('fr', $metadata->language);
    }

    public function testExtractDatePublishFromArticlePublishedTime(): void
    {
        $html = '<html><head><meta property="article:published_time" content="2026-01-15T10:30:00Z"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNotNull($metadata->datePublish);
        $this->assertSame('2026-01-15', $metadata->datePublish->format('Y-m-d'));
    }

    public function testExtractDatePublishFromTimeDatetime(): void
    {
        $html = '<html><head></head><body><time datetime="2026-03-20">March 20</time><p>Content</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNotNull($metadata->datePublish);
        $this->assertSame('2026-03-20', $metadata->datePublish->format('Y-m-d'));
    }

    public function testExtractDatePublishReturnsNullWhenNone(): void
    {
        $html = '<html><head></head><body><p>No date here</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNull($metadata->datePublish);
    }

    public function testExtractContentFromBody(): void
    {
        $html = '<html><head></head><body><h1>Title</h1><p>Paragraph content</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertStringContainsString('Title', $metadata->content);
        $this->assertStringContainsString('Paragraph content', $metadata->content);
    }

    public function testExtractContentPrefersArticleOverBody(): void
    {
        $html = <<<'HTML'
            <html><head></head><body>
                <nav><a href="/">Home</a><a href="/about">About</a></nav>
                <header><h1>Site Header</h1></header>
                <article>
                    <h2>Article Title</h2>
                    <p>This is the real article content that matters for intelligence gathering.</p>
                    <p>Second paragraph with important information.</p>
                </article>
                <aside><h3>Related Articles</h3><ul><li>Other stuff</li></ul></aside>
                <footer><p>Copyright 2026</p></footer>
            </body></html>
            HTML;

        $metadata = $this->extractor->extract($html);

        $this->assertStringContainsString('Article Title', $metadata->content);
        $this->assertStringContainsString('real article content', $metadata->content);
        $this->assertStringNotContainsString('Home', $metadata->content);
        $this->assertStringNotContainsString('Copyright', $metadata->content);
    }

    public function testExtractContentRemovesScriptsAndStyles(): void
    {
        $html = <<<'HTML'
            <html><head></head><body>
                <style>.hidden { display: none; }</style>
                <script>console.log("tracking");</script>
                <p>Real content here.</p>
                <script>gtag('event', 'page_view');</script>
            </body></html>
            HTML;

        $metadata = $this->extractor->extract($html);

        $this->assertStringContainsString('Real content here', $metadata->content);
        $this->assertStringNotContainsString('tracking', $metadata->content);
        $this->assertStringNotContainsString('gtag', $metadata->content);
        $this->assertStringNotContainsString('display: none', $metadata->content);
    }

    public function testExtractContentRemovesNavAndCookieBanners(): void
    {
        $html = <<<'HTML'
            <html><head></head><body>
                <nav class="main-nav"><ul><li>Menu item 1</li><li>Menu item 2</li></ul></nav>
                <div class="cookie-consent">We use cookies. <button>Accept</button></div>
                <main>
                    <h1>Important Analysis</h1>
                    <p>The market trends indicate a significant shift in consumer behavior.</p>
                </main>
                <div class="newsletter-subscribe">Subscribe to our newsletter!</div>
                <div class="social-sharing">Share on Twitter | Facebook</div>
            </body></html>
            HTML;

        $metadata = $this->extractor->extract($html);

        $this->assertStringContainsString('Important Analysis', $metadata->content);
        $this->assertStringContainsString('market trends', $metadata->content);
        $this->assertStringNotContainsString('Menu item', $metadata->content);
        $this->assertStringNotContainsString('cookie', strtolower($metadata->content));
        $this->assertStringNotContainsString('newsletter', strtolower($metadata->content));
        $this->assertStringNotContainsString('Share on', $metadata->content);
    }

    public function testExtractContentRemovesAdsAndSidebars(): void
    {
        $html = <<<'HTML'
            <html><head></head><body>
                <article>
                    <p>Core article text about competitive intelligence.</p>
                </article>
                <aside class="sidebar"><h3>Trending</h3><ul><li>Noise</li></ul></aside>
                <div class="advertisement">Buy our product!</div>
                <div id="comments"><h3>Comments</h3><p>User comment</p></div>
            </body></html>
            HTML;

        $metadata = $this->extractor->extract($html);

        $this->assertStringContainsString('competitive intelligence', $metadata->content);
        $this->assertStringNotContainsString('Buy our product', $metadata->content);
        $this->assertStringNotContainsString('User comment', $metadata->content);
    }

    public function testExtractContentFallsBackToCleanedBodyWhenNoArticle(): void
    {
        $html = <<<'HTML'
            <html><head></head><body>
                <header><h1>Site Name</h1></header>
                <div class="content">
                    <p>Main paragraph one with enough text to be considered real content for the purpose of this test case.</p>
                    <p>Main paragraph two with additional information that is relevant.</p>
                </div>
                <footer>Copyright info</footer>
            </body></html>
            HTML;

        $metadata = $this->extractor->extract($html);

        $this->assertStringContainsString('Main paragraph one', $metadata->content);
        $this->assertStringNotContainsString('Site Name', $metadata->content);
        $this->assertStringNotContainsString('Copyright', $metadata->content);
    }

    public function testExtractFromIso88591Html(): void
    {
        $html = '<html><head><meta charset="ISO-8859-1"><title>Caf' . "\xe9" . ' et cr' . "\xe8" . 'me</title></head><body><p>R' . "\xe9" . 'sum' . "\xe9" . '</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('Café et crème', $metadata->title);
        $this->assertStringContainsString('Résumé', $metadata->content);
    }

    public function testExtractFromWindows1252Html(): void
    {
        $html = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=windows-1252"><title>Stra' . "\xdf" . 'e</title></head><body><p>Content</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('Straße', $metadata->title);
    }

    public function testExtractFromUtf8WithBom(): void
    {
        $html = "\xEF\xBB\xBF" . '<html lang="en"><head><title>BOM Test</title></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('BOM Test', $metadata->title);
        $this->assertSame('en', $metadata->language);
    }

    public function testExtractFromUtf8WithCjkCharacters(): void
    {
        $html = '<html lang="fr"><head><title>日本語テスト</title><meta name="description" content="中文描述测试"></head><body><p>한국어 내용</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('日本語テスト', $metadata->title);
        $this->assertSame('中文描述测试', $metadata->excerpt);
    }

    public function testExtractWithEmojisInTitle(): void
    {
        $html = '<html><head><title>Breaking News 🔥🚀 — AI Takes Over</title></head><body><p>Content with emojis 😀👍🏽</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertStringContainsString('🔥', $metadata->title);
        $this->assertStringContainsString('🚀', $metadata->title);
        $this->assertStringContainsString('😀', $metadata->content);
        $this->assertStringContainsString('👍🏽', $metadata->content);
    }

    public function testExtractWithEmojiInMetaDescription(): void
    {
        $html = '<html><head><meta name="description" content="🌍 Global report on climate 🌡️"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertStringContainsString('🌍', $metadata->excerpt);
        $this->assertStringContainsString('🌡️', $metadata->excerpt);
    }

    public function testExtractDateIso8601WithTimezone(): void
    {
        $html = '<html><head><meta property="article:published_time" content="2026-03-15T14:30:00+02:00"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNotNull($metadata->datePublish);
        $this->assertSame('2026-03-15', $metadata->datePublish->format('Y-m-d'));
    }

    public function testExtractDateShortFormat(): void
    {
        $html = '<html><head></head><body><time datetime="2026-01-15">January 15</time></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNotNull($metadata->datePublish);
        $this->assertSame('2026-01-15', $metadata->datePublish->format('Y-m-d'));
    }

    public function testExtractDateUnixTimestampSeconds(): void
    {
        $html = '<html><head><meta property="article:published_time" content="1774000000"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNotNull($metadata->datePublish);
        $this->assertSame('2026', $metadata->datePublish->format('Y'));
    }

    public function testExtractDateUnixTimestampMilliseconds(): void
    {
        $html = '<html><head><meta property="article:published_time" content="1774000000000"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNotNull($metadata->datePublish);
        $this->assertSame('2026', $metadata->datePublish->format('Y'));
    }

    public function testExtractDateInvalidGraceful(): void
    {
        $html = '<html><head><meta property="article:published_time" content="not-a-date"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNull($metadata->datePublish);
    }

    public function testExtractDateFrenchLocalized(): void
    {
        $html = '<html><head></head><body><time datetime="15 mars 2026">15 mars 2026</time></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNotNull($metadata->datePublish);
        $this->assertSame('2026-03-15', $metadata->datePublish->format('Y-m-d'));
    }

    public function testExtractDateGermanLocalized(): void
    {
        $html = '<html><head></head><body><time datetime="26. März 2026">26. März 2026</time></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNotNull($metadata->datePublish);
        $this->assertSame('2026-03-26', $metadata->datePublish->format('Y-m-d'));
    }

    public function testExtractImageFromOgImage(): void
    {
        $html = '<html><head><meta property="og:image" content="https://example.com/photo.jpg"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('https://example.com/photo.jpg', $metadata->imageUrl);
    }

    public function testExtractImageFromTwitterImage(): void
    {
        $html = '<html><head><meta name="twitter:image" content="https://example.com/card.png"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('https://example.com/card.png', $metadata->imageUrl);
    }

    public function testExtractImageFromArticleImg(): void
    {
        $html = '<html><head></head><body><article><img src="https://cdn.example.com/hero.jpg" alt="Hero"></article></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('https://cdn.example.com/hero.jpg', $metadata->imageUrl);
    }

    public function testExtractImageSkipsTrackingPixel(): void
    {
        $html = '<html><head></head><body><article><img src="https://tracker.example.com/pixel.gif" width="1" height="1"><img src="https://cdn.example.com/real-photo.jpg" alt="Photo"></article></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('https://cdn.example.com/real-photo.jpg', $metadata->imageUrl);
    }

    public function testExtractImageSkipsLogosAndFavicons(): void
    {
        $html = '<html><head></head><body><img src="https://example.com/favicon.ico"><img src="https://example.com/logo.png" class="site-logo"><article><img src="https://cdn.example.com/content-image.jpg"></article></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('https://cdn.example.com/content-image.jpg', $metadata->imageUrl);
    }

    public function testExtractImageReturnsNullWhenNone(): void
    {
        $html = '<html><head></head><body><p>No images here</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNull($metadata->imageUrl);
    }

    public function testExtractImageOgTakesPriorityOverBodyImg(): void
    {
        $html = '<html><head><meta property="og:image" content="https://example.com/og-image.jpg"></head><body><article><img src="https://example.com/body-image.jpg"></article></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('https://example.com/og-image.jpg', $metadata->imageUrl);
    }

    public function testExtractAuthorFromMetaTag(): void
    {
        $html = '<html><head><meta name="author" content="Jane Doe"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('Jane Doe', $metadata->author);
    }

    public function testExtractAuthorFromArticleAuthor(): void
    {
        $html = '<html><head><meta property="article:author" content="John Smith"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('John Smith', $metadata->author);
    }

    public function testExtractAuthorFromSchemaOrg(): void
    {
        $html = '<html><head></head><body><span itemprop="author"><span itemprop="name">Dr. Watson</span></span><p>Content</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('Dr. Watson', $metadata->author);
    }

    public function testExtractAuthorFromBylineClass(): void
    {
        $html = '<html><head></head><body><div class="byline">By Sarah Connor</div><p>Content</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('By Sarah Connor', $metadata->author);
    }

    public function testExtractAuthorReturnsNullWhenNone(): void
    {
        $html = '<html><head></head><body><p>No author</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNull($metadata->author);
    }

    public function testExtractAuthorSkipsFacebookUrl(): void
    {
        $html = '<html><head><meta property="article:author" content="https://facebook.com/johndoe"><meta name="author" content=""></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        // Facebook URL should be skipped, no fallback found
        $this->assertNull($metadata->author);
    }

    public function testExtractSiteNameFromOgSiteName(): void
    {
        $html = '<html><head><meta property="og:site_name" content="Le Monde"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('Le Monde', $metadata->siteName);
    }

    public function testExtractSiteNameFromApplicationName(): void
    {
        $html = '<html><head><meta name="application-name" content="Reuters"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('Reuters', $metadata->siteName);
    }

    public function testExtractSiteNameFromSchemaPublisher(): void
    {
        $html = '<html><head></head><body><div itemprop="publisher"><span itemprop="name">BBC News</span></div><p>Content</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('BBC News', $metadata->siteName);
    }

    public function testExtractSiteNameReturnsNullWhenNone(): void
    {
        $html = '<html><head></head><body><p>No site name</p></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNull($metadata->siteName);
    }

    public function testLanguageDetectionFallsBackToContentAnalysis(): void
    {
        // No lang attribute, but French content
        $html = '<html><head></head><body><p>Ceci est un article en français sur la politique européenne et les relations internationales entre les pays membres.</p></body></html>';

        $metadata = $this->extractor->extract($html);

        // With the language detector injected, this should detect 'fr'
        // Without it (unit test), falls back to default 'fr'
        $this->assertSame('fr', $metadata->language);
    }

    public function testExtractCanonicalFromLinkTag(): void
    {
        $html = '<html><head><link rel="canonical" href="https://example.com/article/123"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('https://example.com/article/123', $metadata->canonicalUrl);
    }

    public function testExtractCanonicalFromOgUrl(): void
    {
        $html = '<html><head><meta property="og:url" content="https://example.com/page"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('https://example.com/page', $metadata->canonicalUrl);
    }

    public function testExtractCanonicalLinkTakesPriorityOverOgUrl(): void
    {
        $html = '<html><head><link rel="canonical" href="https://example.com/canonical"><meta property="og:url" content="https://example.com/og"></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame('https://example.com/canonical', $metadata->canonicalUrl);
    }

    public function testExtractCanonicalReturnsNullWhenNone(): void
    {
        $html = '<html><head></head><body>Content</body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNull($metadata->canonicalUrl);
    }

    public function testExtractFullPage(): void
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <title>Full Test Page</title>
                <meta name="description" content="A comprehensive test page">
                <meta property="article:published_time" content="2026-02-10T08:00:00Z">
            </head>
            <body>
                <h1>Welcome</h1>
                <p>This is the main content of the page.</p>
            </body>
            </html>
            HTML;

        $metadata = $this->extractor->extract($html);

        $this->assertSame('Full Test Page', $metadata->title);
        $this->assertSame('A comprehensive test page', $metadata->excerpt);
        $this->assertSame('en', $metadata->language);
        $this->assertNotNull($metadata->datePublish);
        $this->assertSame('2026-02-10', $metadata->datePublish->format('Y-m-d'));
        $this->assertStringContainsString('main content', $metadata->content);
    }
}
