<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document;

use App\Domain\Document\CanonicalUrlExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CanonicalUrlExtractor::class)]
class CanonicalUrlExtractorTest extends TestCase
{
    private CanonicalUrlExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new CanonicalUrlExtractor();
    }

    public function testReturnsNullWhenAllInputsAreNull(): void
    {
        self::assertNull($this->extractor->extract(null, null, null));
    }

    public function testReturnsNullWhenAllInputsAreEmpty(): void
    {
        self::assertNull($this->extractor->extract('', '', ''));
    }

    public function testProviderCanonicalUrlTakesPriorityOverHtml(): void
    {
        $html = '<html><head><link rel="canonical" href="https://html-canonical.example.com/article"></head></html>';
        $result = $this->extractor->extract(
            'https://provider.example.com/article',
            $html,
            'https://source.example.com/article?utm_source=newsletter',
        );

        self::assertSame('https://provider.example.com/article', $result);
    }

    public function testLinkRelCanonicalIsExtractedFromHtml(): void
    {
        $html = '<html><head><link rel="canonical" href="https://example.com/article"></head></html>';
        $result = $this->extractor->extract(null, $html, 'https://example.com/article?utm_source=foo');

        self::assertSame('https://example.com/article', $result);
    }

    public function testLinkRelCanonicalIsExtractedWithSingleQuotes(): void
    {
        $html = "<html><head><link rel='canonical' href='https://example.com/article'></head></html>";
        $result = $this->extractor->extract(null, $html, null);

        self::assertSame('https://example.com/article', $result);
    }

    public function testLinkRelCanonicalIsExtractedWithAttributeOrderReversed(): void
    {
        $html = '<html><head><link href="https://example.com/article" rel="canonical"></head></html>';
        $result = $this->extractor->extract(null, $html, null);

        self::assertSame('https://example.com/article', $result);
    }

    public function testOgUrlIsUsedAsFallbackWhenCanonicalIsAbsent(): void
    {
        $html = '<html><head><meta property="og:url" content="https://example.com/og-article"></head></html>';
        $result = $this->extractor->extract(null, $html, 'https://example.com/source');

        self::assertSame('https://example.com/og-article', $result);
    }

    public function testCanonicalIsPreferredOverOgUrl(): void
    {
        $html = '<html><head>'
            . '<link rel="canonical" href="https://example.com/canonical">'
            . '<meta property="og:url" content="https://example.com/og">'
            . '</head></html>';
        $result = $this->extractor->extract(null, $html, null);

        self::assertSame('https://example.com/canonical', $result);
    }

    public function testSourceUrlIsUsedWhenNoCanonicalOrOgUrl(): void
    {
        $result = $this->extractor->extract(null, '<html></html>', 'https://example.com/source');

        self::assertSame('https://example.com/source', $result);
    }

    public function testRelativeCanonicalUrlIsResolvedAgainstSourceUrl(): void
    {
        $html = '<html><head><link rel="canonical" href="/articles/42"></head></html>';
        $result = $this->extractor->extract(null, $html, 'https://example.com/section/article');

        self::assertSame('https://example.com/articles/42', $result);
    }

    public function testProtocolRelativeCanonicalUrlIsResolved(): void
    {
        $html = '<html><head><link rel="canonical" href="//example.com/article"></head></html>';
        $result = $this->extractor->extract(null, $html, 'https://source.example.com/foo');

        self::assertSame('https://example.com/article', $result);
    }

    #[DataProvider('trackingParamsProvider')]
    public function testTrackingParamsAreStripped(string $input, string $expected): void
    {
        self::assertSame($expected, $this->extractor->extract(null, null, $input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function trackingParamsProvider(): array
    {
        return [
            'utm_source removed' => [
                'https://example.com/article?utm_source=newsletter',
                'https://example.com/article',
            ],
            'all utm_* removed' => [
                'https://example.com/article?utm_source=a&utm_medium=b&utm_campaign=c&utm_term=d&utm_content=e',
                'https://example.com/article',
            ],
            'fbclid removed' => ['https://example.com/article?fbclid=IwAR0abc', 'https://example.com/article'],
            'gclid removed' => ['https://example.com/article?gclid=Cj0KCQjw', 'https://example.com/article'],
            'ref removed' => ['https://example.com/article?ref=homepage', 'https://example.com/article'],
            'source removed' => ['https://example.com/article?source=twitter', 'https://example.com/article'],
            'mixed tracking and useful params' => [
                'https://example.com/article?id=42&utm_source=foo&page=2&fbclid=abc',
                'https://example.com/article?id=42&page=2',
            ],
            'only tracking params strips entire query' => [
                'https://example.com/article?utm_source=a&fbclid=b',
                'https://example.com/article',
            ],
        ];
    }

    public function testRemainingQueryParamsAreSortedAlphabetically(): void
    {
        $result = $this->extractor->extract(null, null, 'https://example.com/article?z=1&a=2&m=3');

        self::assertSame('https://example.com/article?a=2&m=3&z=1', $result);
    }

    public function testSchemeIsLowercased(): void
    {
        $result = $this->extractor->extract(null, null, 'HTTPS://example.com/article');

        self::assertSame('https://example.com/article', $result);
    }

    public function testHostIsLowercased(): void
    {
        $result = $this->extractor->extract(null, null, 'https://EXAMPLE.COM/Article');

        self::assertSame('https://example.com/Article', $result);
    }

    public function testPathCaseIsPreserved(): void
    {
        $result = $this->extractor->extract(null, null, 'https://example.com/Path/With/CamelCase');

        self::assertSame('https://example.com/Path/With/CamelCase', $result);
    }

    public function testFragmentIsStripped(): void
    {
        $result = $this->extractor->extract(null, null, 'https://example.com/article#section-2');

        self::assertSame('https://example.com/article', $result);
    }

    public function testReturnsNullForJavascriptScheme(): void
    {
        self::assertNull($this->extractor->extract('javascript:alert(1)', null, null));
    }

    public function testReturnsNullForDataScheme(): void
    {
        self::assertNull($this->extractor->extract('data:text/html,<h1>x</h1>', null, null));
    }

    public function testReturnsNullForFileScheme(): void
    {
        self::assertNull($this->extractor->extract(null, null, 'file:///etc/passwd'));
    }

    public function testReturnsNullForUnparseableUrl(): void
    {
        self::assertNull($this->extractor->extract(null, null, '://not-a-url'));
    }

    public function testIgnoresCanonicalLinkWithJavascriptScheme(): void
    {
        $html = '<html><head><link rel="canonical" href="javascript:alert(1)"></head></html>';
        $result = $this->extractor->extract(null, $html, 'https://example.com/source');

        self::assertSame('https://example.com/source', $result);
    }

    public function testIgnoresEmptyCanonicalHref(): void
    {
        $html = '<html><head><link rel="canonical" href=""></head></html>';
        $result = $this->extractor->extract(null, $html, 'https://example.com/source');

        self::assertSame('https://example.com/source', $result);
    }

    public function testInternationalizedDomainNameIsConvertedToPunycode(): void
    {
        // IDN hosts are normalized to ASCII Punycode so the Unicode spelling
        // and the xn-- spelling collapse to the same dedup key.
        $result = $this->extractor->extract(null, null, 'https://Müller.example.COM/article');

        self::assertSame('https://xn--mller-kva.example.com/article', $result);
    }

    public function testProviderCanonicalIsAlsoNormalized(): void
    {
        $result = $this->extractor->extract('HTTPS://EXAMPLE.COM/article?utm_source=foo&z=1&a=2', null, null);

        self::assertSame('https://example.com/article?a=2&z=1', $result);
    }

    public function testSyndicatedAfpArticleProducesSameCanonicalUrl(): void
    {
        $html = '<html><head><link rel="canonical" href="https://www.afp.com/article/world-news-2026"></head></html>';

        $lemonde = $this->extractor->extract(null, $html, 'https://lemonde.fr/republished?utm_source=newsletter');
        $lefigaro = $this->extractor->extract(null, $html, 'https://lefigaro.fr/republished?fbclid=abc');

        self::assertSame($lemonde, $lefigaro);
        self::assertSame('https://www.afp.com/article/world-news-2026', $lemonde);
    }

    public function testTrackingOnlyVariantsCollapseToSameCanonicalUrl(): void
    {
        $first = $this->extractor->extract(null, null, 'https://example.com/article?utm_source=a&utm_medium=b');
        $second = $this->extractor->extract(null, null, 'https://example.com/article?fbclid=xyz');

        self::assertSame($first, $second);
    }

    public function testTrailingSlashIsPreservedOnRoot(): void
    {
        $result = $this->extractor->extract(null, null, 'https://example.com/');

        self::assertSame('https://example.com/', $result);
    }

    public function testQueryWithoutValueIsRetained(): void
    {
        $result = $this->extractor->extract(null, null, 'https://example.com/search?q=test&debug');

        self::assertSame('https://example.com/search?debug=&q=test', $result);
    }

    public function testWhitespaceInProviderUrlIsTrimmed(): void
    {
        $result = $this->extractor->extract("  https://example.com/article  \n", null, null);

        self::assertSame('https://example.com/article', $result);
    }

    public function testHtmlWithoutCanonicalAndNoSourceReturnsNull(): void
    {
        $html = '<html><head><title>No canonical here</title></head></html>';
        self::assertNull($this->extractor->extract(null, $html, null));
    }

    public function testNonHttpProviderUrlIsRejected(): void
    {
        self::assertNull($this->extractor->extract('ftp://example.com/article', null, null));
    }

    // ───────────────────────── Edge cases ─────────────────────────

    public function testUserInfoIsStrippedFromUrl(): void
    {
        // userinfo (user:pass@) must never appear in a canonical URL: it leaks
        // credentials and changes the dedup key for two otherwise-identical pages.
        $result = $this->extractor->extract(null, null, 'https://alice:s3cret@example.com/article');

        self::assertSame('https://example.com/article', $result);
    }

    public function testDefaultHttpsPortIsStripped(): void
    {
        $result = $this->extractor->extract(null, null, 'https://example.com:443/article');

        self::assertSame('https://example.com/article', $result);
    }

    public function testDefaultHttpPortIsStripped(): void
    {
        $result = $this->extractor->extract(null, null, 'http://example.com:80/article');

        self::assertSame('http://example.com/article', $result);
    }

    public function testNonDefaultPortIsPreserved(): void
    {
        $result = $this->extractor->extract(null, null, 'https://example.com:8443/article');

        self::assertSame('https://example.com:8443/article', $result);
    }

    public function testEmptyPathIsNormalizedToSlash(): void
    {
        // example.com and example.com/ resolve to the same document.
        $result = $this->extractor->extract(null, null, 'https://example.com');

        self::assertSame('https://example.com/', $result);
    }

    public function testFirstCanonicalLinkWinsWhenMultiplePresent(): void
    {
        $html = '<html><head>'
            . '<link rel="canonical" href="https://example.com/first">'
            . '<link rel="canonical" href="https://example.com/second">'
            . '</head></html>';
        $result = $this->extractor->extract(null, $html, null);

        self::assertSame('https://example.com/first', $result);
    }

    public function testCanonicalInsideHtmlCommentIsIgnored(): void
    {
        // Authors sometimes leave a <link rel="canonical"> inside a comment as a
        // future-rollout marker — it must not be treated as the live canonical.
        $html = '<html><head>'
            . '<!-- <link rel="canonical" href="https://example.com/draft"> -->'
            . '<link rel="canonical" href="https://example.com/published">'
            . '</head></html>';
        $result = $this->extractor->extract(null, $html, null);

        self::assertSame('https://example.com/published', $result);
    }

    public function testCanonicalLinkWithoutHrefIsIgnored(): void
    {
        $html = '<html><head><link rel="canonical"></head></html>';
        $result = $this->extractor->extract(null, $html, 'https://example.com/source');

        self::assertSame('https://example.com/source', $result);
    }

    public function testWhitespaceInCanonicalHrefIsTrimmed(): void
    {
        $html = '<html><head><link rel="canonical" href="  https://example.com/article  "></head></html>';
        $result = $this->extractor->extract(null, $html, null);

        self::assertSame('https://example.com/article', $result);
    }

    public function testCanonicalHrefWithFragmentDropsTheFragment(): void
    {
        $html = '<html><head><link rel="canonical" href="https://example.com/article#chapter-2"></head></html>';
        $result = $this->extractor->extract(null, $html, null);

        self::assertSame('https://example.com/article', $result);
    }

    public function testMixedCaseCanonicalAttributeIsMatched(): void
    {
        $html = '<html><head><LINK Rel="Canonical" Href="https://example.com/article"></head></html>';
        $result = $this->extractor->extract(null, $html, null);

        self::assertSame('https://example.com/article', $result);
    }

    public function testUppercaseTrackingParamIsStripped(): void
    {
        // Some publishers emit uppercase utm_* params. Comparison must be
        // case-insensitive so dedup is not bypassed by tag casing.
        $result = $this->extractor->extract(null, null, 'https://example.com/article?UTM_SOURCE=foo&id=42');

        self::assertSame('https://example.com/article?id=42', $result);
    }

    public function testTrackingParamIsAlsoStrippedFromOgUrl(): void
    {
        $html = '<html><head><meta property="og:url" content="https://example.com/og?utm_source=fb"></head></html>';
        $result = $this->extractor->extract(null, $html, null);

        self::assertSame('https://example.com/og', $result);
    }

    public function testParentRelativePathIsResolvedCorrectly(): void
    {
        // /a/b/c + ../x → /a/x  (RFC 3986 §5.2.4)
        $html = '<html><head><link rel="canonical" href="../canonical"></head></html>';
        $result = $this->extractor->extract(null, $html, 'https://example.com/section/page/article');

        self::assertSame('https://example.com/section/canonical', $result);
    }

    public function testPathRelativeWithoutDotsIsResolved(): void
    {
        $html = '<html><head><link rel="canonical" href="other-article"></head></html>';
        $result = $this->extractor->extract(null, $html, 'https://example.com/section/article');

        self::assertSame('https://example.com/section/other-article', $result);
    }

    public function testParentRelativePathCannotEscapeRoot(): void
    {
        // /../../foo from base / never goes above root.
        $html = '<html><head><link rel="canonical" href="../../../../foo"></head></html>';
        $result = $this->extractor->extract(null, $html, 'https://example.com/article');

        self::assertSame('https://example.com/foo', $result);
    }

    public function testPercentEncodedQueryValueIsNormalized(): void
    {
        // parse_str decodes, http_build_query re-encodes — should round-trip cleanly.
        $result = $this->extractor->extract(null, null, 'https://example.com/search?q=hello%20world&utm_source=foo');

        self::assertSame('https://example.com/search?q=hello%20world', $result);
    }

    public function testUnicodePathIsPreserved(): void
    {
        $result = $this->extractor->extract(null, null, 'https://example.com/articles/naïveté');

        self::assertSame('https://example.com/articles/naïveté', $result);
    }

    public function testEmptyQueryStringIsDropped(): void
    {
        $result = $this->extractor->extract(null, null, 'https://example.com/article?');

        self::assertSame('https://example.com/article', $result);
    }

    public function testQueryWithOnlyAmpersandsIsDropped(): void
    {
        $result = $this->extractor->extract(null, null, 'https://example.com/article?&&&');

        self::assertSame('https://example.com/article', $result);
    }

    public function testInvalidCanonicalFallsBackToOgUrlInSameHtml(): void
    {
        // canonical present but its scheme is forbidden — must skip to og:url.
        $html = '<html><head>'
            . '<link rel="canonical" href="javascript:alert(1)">'
            . '<meta property="og:url" content="https://example.com/og">'
            . '</head></html>';
        $result = $this->extractor->extract(null, $html, 'https://source.example.com/x');

        self::assertSame('https://example.com/og', $result);
    }

    public function testInvalidCanonicalAndInvalidOgFallBackToSourceUrl(): void
    {
        $html = '<html><head>'
            . '<link rel="canonical" href="javascript:alert(1)">'
            . '<meta property="og:url" content="data:text/html,x">'
            . '</head></html>';
        $result = $this->extractor->extract(null, $html, 'https://example.com/source');

        self::assertSame('https://example.com/source', $result);
    }

    public function testInvalidProviderUrlFallsBackToHtml(): void
    {
        $html = '<html><head><link rel="canonical" href="https://example.com/canonical"></head></html>';
        $result = $this->extractor->extract('javascript:alert(1)', $html, null);

        self::assertSame('https://example.com/canonical', $result);
    }

    public function testHostOnlyDifferenceIsCaseInsensitive(): void
    {
        $a = $this->extractor->extract(null, null, 'https://Example.COM/article');
        $b = $this->extractor->extract(null, null, 'https://example.com/article');

        self::assertSame($a, $b);
    }

    public function testCanonicalHostnameWithLeadingWwwIsPreserved(): void
    {
        // Stripping "www." is publisher-specific and out of scope for Stage 0.
        $result = $this->extractor->extract(null, null, 'https://www.example.com/article');

        self::assertSame('https://www.example.com/article', $result);
    }

    public function testQueryParameterValueWithEqualsIsHandled(): void
    {
        $result = $this->extractor->extract(null, null, 'https://example.com/x?token=abc=def');

        self::assertSame('https://example.com/x?token=abc%3Ddef', $result);
    }

    // ───────────────────── Punycode / IDN / emoji ─────────────────────

    public function testAlreadyPunycodeHostIsPreservedAndLowercased(): void
    {
        $result = $this->extractor->extract(null, null, 'https://XN--MLLER-KVA.example.com/article');

        self::assertSame('https://xn--mller-kva.example.com/article', $result);
    }

    public function testUnicodeAndPunycodeSpellingsCollapseToSameCanonical(): void
    {
        // The whole point of IDN normalization for dedup: müller.de and the
        // xn--mller-kva.de spelling must produce the same canonical URL.
        $unicode = $this->extractor->extract(null, null, 'https://müller.example.com/article');
        $punycode = $this->extractor->extract(null, null, 'https://xn--mller-kva.example.com/article');

        self::assertSame($unicode, $punycode);
        self::assertSame('https://xn--mller-kva.example.com/article', $unicode);
    }

    public function testGermanIdnIsConvertedToPunycode(): void
    {
        $result = $this->extractor->extract(null, null, 'https://münchen.de/news');

        self::assertSame('https://xn--mnchen-3ya.de/news', $result);
    }

    public function testCyrillicIdnIsConvertedToPunycode(): void
    {
        $result = $this->extractor->extract(null, null, 'https://пример.рф/article');

        self::assertSame('https://xn--e1afmkfd.xn--p1ai/article', $result);
    }

    public function testJapaneseIdnIsConvertedToPunycode(): void
    {
        $result = $this->extractor->extract(null, null, 'https://日本.example.com/news');

        self::assertSame('https://xn--wgv71a.example.com/news', $result);
    }

    public function testEmojiInPathIsPreservedVerbatim(): void
    {
        // PHP's parse_url and our normalizer do not transcode the path; a
        // Unicode path stays a Unicode path (callers can percent-encode if needed).
        $result = $this->extractor->extract(null, null, 'https://example.com/articles/🎉');

        self::assertSame('https://example.com/articles/🎉', $result);
    }

    public function testEmojiInQueryValueIsPercentEncoded(): void
    {
        // http_build_query with PHP_QUERY_RFC3986 percent-encodes non-ASCII bytes.
        $result = $this->extractor->extract(null, null, 'https://example.com/search?topic=🎉&utm_source=fb');

        self::assertSame('https://example.com/search?topic=%F0%9F%8E%89', $result);
    }

    public function testPercentEncodedEmojiInQueryRoundTripsToTheSameForm(): void
    {
        $a = $this->extractor->extract(null, null, 'https://example.com/search?q=🎉');
        $b = $this->extractor->extract(null, null, 'https://example.com/search?q=%F0%9F%8E%89');

        self::assertSame($a, $b);
    }

    public function testEmojiInHostIsConvertedToPunycode(): void
    {
        // Emoji TLDs are deprecated, but the few that exist (e.g. .ws) still
        // round-trip through Punycode.
        $result = $this->extractor->extract(null, null, 'https://i❤.ws/article');

        self::assertSame('https://xn--i-7iq.ws/article', $result);
    }

    public function testCanonicalHrefInUnicodeHostIsConvertedToPunycode(): void
    {
        $html = '<html><head><link rel="canonical" href="https://münchen.de/article"></head></html>';
        $result = $this->extractor->extract(null, $html, null);

        self::assertSame('https://xn--mnchen-3ya.de/article', $result);
    }

    public function testMalformedIdnFallsBackToLowercaseHost(): void
    {
        // Hosts with stray double-hyphens at positions 3–4 are invalid IDNs.
        // The normalizer must not crash; it falls back to a plain lowercase.
        $result = $this->extractor->extract(null, null, 'https://aa--bb.example.com/article');

        // Either Punycode-encoded (some intl builds accept it) or unchanged ASCII.
        self::assertNotNull($result);
        self::assertStringContainsString('/article', $result);
        self::assertStringStartsWith('https://', $result);
    }
}
