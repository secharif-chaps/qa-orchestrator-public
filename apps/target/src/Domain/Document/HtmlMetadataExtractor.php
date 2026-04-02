<?php

declare(strict_types=1);

namespace App\Domain\Document;

use App\Domain\Language\LanguageDetectorInterface;

readonly class HtmlMetadataExtractor
{
    private const string DEFAULT_LANGUAGE = 'fr';

    public function __construct(
        private readonly ?LanguageDetectorInterface $languageDetector = null,
    ) {
    }

    public function extract(string $html, ?string $sourceUrl = null): HtmlMetadata
    {
        $html = $this->ensureUtf8($html);

        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, ~0], 'UTF-8'), \LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);

        // Resolve base URL from <base href>, canonical, or source URL
        $baseUrl = $this->resolveBaseUrl($xpath, $sourceUrl);

        // Extract metadata BEFORE content (extractContent mutates the DOM via sanitizeHtmlSecurity)
        $title = $this->extractTitle($xpath);
        $excerpt = $this->extractExcerpt($xpath, '');
        $datePublish = $this->extractDatePublish($xpath);
        $imageUrl = $this->extractImageUrl($xpath);
        $canonicalUrl = $this->extractCanonicalUrl($xpath);
        $author = $this->extractAuthor($xpath);
        $siteName = $this->extractSiteName($xpath);

        // Extract content last (strips class, style, data-* attributes from DOM)
        $content = $this->extractContent($xpath);
        $language = $this->detectLanguage($xpath, $dom, $content);

        // Re-extract excerpt from content if meta tags didn't provide one
        if ('No description available' === $excerpt) {
            $stripped = trim(strip_tags($content));
            if ('' !== $stripped) {
                $excerpt = mb_substr($stripped, 0, 200);
            }
        }

        // Resolve relative URLs in content and image
        if (null !== $baseUrl) {
            $content = $this->resolveRelativeUrls($content, $baseUrl);
            if (null !== $imageUrl && !$this->isAbsoluteUrl($imageUrl)) {
                $imageUrl = $this->makeAbsoluteUrl($imageUrl, $baseUrl);
            }
        }

        return new HtmlMetadata(
            title: $title,
            excerpt: $excerpt,
            content: $content,
            language: $language,
            datePublish: $datePublish,
            imageUrl: $imageUrl,
            canonicalUrl: $canonicalUrl,
            author: $author,
            siteName: $siteName,
        );
    }

    private function extractTitle(\DOMXPath $xpath): string
    {
        $titleNode = $xpath->query('//title');
        if ($titleNode instanceof \DOMNodeList && $titleNode->length > 0) {
            $node = $titleNode->item(0);
            $text = $node instanceof \DOMNode ? trim($node->textContent) : '';
            if ('' !== $text) {
                return $text;
            }
        }

        $ogTitle = $this->getMetaContent($xpath, 'og:title', 'property');
        if ('' !== $ogTitle) {
            return $ogTitle;
        }

        $h1 = $xpath->query('//h1');
        if ($h1 instanceof \DOMNodeList && $h1->length > 0) {
            $node = $h1->item(0);
            $text = $node instanceof \DOMNode ? trim($node->textContent) : '';
            if ('' !== $text) {
                return $text;
            }
        }

        return HtmlMetadata::UNTITLED;
    }

    private function extractExcerpt(\DOMXPath $xpath, string $textContent): string
    {
        $description = $this->getMetaContent($xpath, 'description', 'name');
        if ('' !== $description) {
            return mb_substr($description, 0, 1000);
        }

        $ogDescription = $this->getMetaContent($xpath, 'og:description', 'property');
        if ('' !== $ogDescription) {
            return mb_substr($ogDescription, 0, 1000);
        }

        $stripped = trim(strip_tags($textContent));
        if ('' !== $stripped) {
            return mb_substr($stripped, 0, 200);
        }

        return 'No description available';
    }

    /**
     * Extract the main content from HTML, removing boilerplate (nav, footer, ads, scripts, etc.).
     * Priority: <article> → <main> → <[role=main]> → cleaned <body>.
     */
    private function extractContent(\DOMXPath $xpath): string
    {
        // Try semantic content containers first (most likely to be article content)
        foreach ([
            '//article',
            '//main',
            '//*[@role="main"]',
            '//*[contains(@class,"article-body")]',
            '//*[contains(@class,"post-content")]',
            '//*[contains(@class,"entry-content")]',
            '//*[contains(@class,"rendered-post")]',
        ] as $query) {
            $nodes = $xpath->query($query);
            if ($nodes instanceof \DOMNodeList && $nodes->length > 0) {
                $node = $nodes->item(0);
                if ($node instanceof \DOMElement) {
                    $this->removeBoilerplateNodes($node);
                    $html = $this->getCleanedInnerHtml($node);
                    if (mb_strlen(strip_tags($html)) > 100) {
                        return $html;
                    }
                }
            }
        }

        // Fallback: cleaned <body> with boilerplate removed
        $body = $xpath->query('//body');
        if ($body instanceof \DOMNodeList && $body->length > 0) {
            $bodyNode = $body->item(0);
            if ($bodyNode instanceof \DOMElement) {
                $this->removeBoilerplateNodes($bodyNode);

                return $this->getCleanedInnerHtml($bodyNode);
            }
        }

        return $this->stripToText($xpath);
    }

    private function removeBoilerplateNodes(\DOMElement $root): void
    {
        $tagsToRemove = ['script', 'style', 'noscript', 'iframe', 'svg', 'nav', 'header', 'footer', 'aside', 'form'];

        $classesToRemove = [
            'nav', 'navbar', 'navigation', 'menu', 'sidebar', 'widget',
            'footer', 'header', 'banner', 'breadcrumb',
            'cookie', 'consent', 'gdpr', 'popup', 'modal', 'overlay',
            'ad', 'ads', 'advert', 'advertisement', 'sponsor',
            'social', 'share', 'sharing', 'social-links',
            'comment', 'comments', 'disqus',
            'related', 'recommended', 'suggestion',
            'newsletter', 'subscribe',
        ];

        $rolesToRemove = ['navigation', 'banner', 'contentinfo', 'complementary'];

        $nodesToRemove = [];

        // Collect nodes to remove (cannot modify DOM while iterating)
        $allElements = $root->getElementsByTagName('*');
        for ($i = 0; $i < $allElements->length; ++$i) {
            $element = $allElements->item($i);
            if (!$element instanceof \DOMElement) {
                continue;
            }

            // Remove by tag name
            if (\in_array(strtolower($element->tagName), $tagsToRemove, true)) {
                $nodesToRemove[] = $element;

                continue;
            }

            // Remove by ARIA role
            $role = strtolower($element->getAttribute('role'));
            if ('' !== $role && \in_array($role, $rolesToRemove, true)) {
                $nodesToRemove[] = $element;

                continue;
            }

            // Remove by class or id matching boilerplate patterns
            $class = strtolower($element->getAttribute('class'));
            $id = strtolower($element->getAttribute('id'));
            $combined = $class . ' ' . $id;

            foreach ($classesToRemove as $pattern) {
                if (str_contains($combined, $pattern)) {
                    $nodesToRemove[] = $element;

                    break;
                }
            }
        }

        // Remove collected nodes (reverse to avoid index shifting)
        foreach (array_reverse($nodesToRemove) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function getCleanedInnerHtml(\DOMElement $element): string
    {
        $this->sanitizeHtmlSecurity($element);

        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument?->saveHTML($child) ?? '';
        }

        // Collapse excessive whitespace while preserving paragraph breaks
        $html = (string) preg_replace('/\n{3,}/', "\n\n", $html);
        $html = (string) preg_replace('/[ \t]+/', ' ', $html);

        return trim($html);
    }

    /**
     * Sanitize HTML for safe storage and display:
     * - Strip class, style, id, and data-* attributes (prevent CSS/JS inheritance)
     * - Add rel="nofollow noopener noreferrer" + target="_blank" on links
     * - Remove event handler attributes (onclick, onerror, onload, etc.)
     * - Remove javascript: URLs
     */
    private function sanitizeHtmlSecurity(\DOMElement $root): void
    {
        $allElements = $root->getElementsByTagName('*');
        $eventHandlers = [
            'onclick', 'ondblclick', 'onmousedown', 'onmouseup', 'onmouseover', 'onmousemove', 'onmouseout',
            'onkeypress', 'onkeydown', 'onkeyup', 'onfocus', 'onblur', 'onchange', 'onsubmit', 'onreset',
            'onselect', 'onerror', 'onload', 'onunload', 'onabort', 'onresize', 'onscroll',
        ];

        for ($i = 0; $i < $allElements->length; ++$i) {
            $element = $allElements->item($i);
            if (!$element instanceof \DOMElement) {
                continue;
            }

            // Strip class, style, and id attributes (prevent CSS inheritance from source site)
            $element->removeAttribute('class');
            $element->removeAttribute('style');
            $element->removeAttribute('id');

            // Strip all data-* attributes
            $dataAttrs = [];
            foreach ($element->attributes as $attr) {
                if (str_starts_with($attr->name, 'data-')) {
                    $dataAttrs[] = $attr->name;
                }
            }
            foreach ($dataAttrs as $attr) {
                $element->removeAttribute($attr);
            }

            // Sanitize <a> tags: add nofollow + noopener + target _blank
            if ('a' === strtolower($element->tagName)) {
                $href = $element->getAttribute('href');
                if ('' !== $href) {
                    if (str_starts_with(strtolower(trim($href)), 'javascript:')) {
                        $element->setAttribute('href', '#');
                    }
                    $element->setAttribute('rel', 'nofollow noopener noreferrer');
                    $element->setAttribute('target', '_blank');
                }
            }

            // Remove all event handler attributes
            foreach ($eventHandlers as $attr) {
                if ($element->hasAttribute($attr)) {
                    $element->removeAttribute($attr);
                }
            }

            // Remove javascript: in src attributes
            $src = $element->getAttribute('src');
            if ('' !== $src && str_starts_with(strtolower(trim($src)), 'javascript:')) {
                $element->removeAttribute('src');
            }
        }
    }

    /**
     * Detect language using HTML meta tags first, then content-based detection via LanguageDetectorInterface.
     */
    private function detectLanguage(\DOMXPath $xpath, \DOMDocument $dom, string $content): string
    {
        // 1. HTML meta tags (fast, declared by publisher)
        $htmlElement = $dom->documentElement;
        if ($htmlElement instanceof \DOMElement) {
            $lang = $htmlElement->getAttribute('lang');
            if ('' !== $lang) {
                $normalized = $this->normalizeLanguage($lang);
                // Trust meta tag only if it's a supported language
                if (self::DEFAULT_LANGUAGE !== $normalized || 'fr' === strtolower(explode('-', $lang)[0])) {
                    return $normalized;
                }
            }
        }

        $contentLang = $this->getMetaContent($xpath, 'content-language', 'http-equiv');
        if ('' !== $contentLang) {
            return $this->normalizeLanguage($contentLang);
        }

        $ogLocale = $this->getMetaContent($xpath, 'og:locale', 'property');
        if ('' !== $ogLocale) {
            return $this->normalizeLanguage($ogLocale);
        }

        // 2. Content-based detection (slower but accurate, handles wrong/missing meta tags)
        if (null !== $this->languageDetector) {
            $textForDetection = strip_tags($content);
            // Use first 2000 chars for speed — enough for reliable detection
            $textForDetection = mb_substr($textForDetection, 0, 2000);

            if (mb_strlen($textForDetection) >= 20) {
                $detected = $this->languageDetector->detect($textForDetection);
                if (null !== $detected->languageCode && $detected->confidence >= LanguageDetectorInterface::CONFIDENCE_THRESHOLD) {
                    return $detected->languageCode;
                }
            }
        }

        return self::DEFAULT_LANGUAGE;
    }

    private function extractDatePublish(\DOMXPath $xpath): ?\DateTimeImmutable
    {
        $articleDate = $this->getMetaContent($xpath, 'article:published_time', 'property');
        if ('' !== $articleDate) {
            return $this->parseDate($articleDate);
        }

        $datePublished = $this->getMetaContent($xpath, 'datePublished', 'itemprop');
        if ('' !== $datePublished) {
            return $this->parseDate($datePublished);
        }

        $timeNodes = $xpath->query('//time[@datetime]');
        if ($timeNodes instanceof \DOMNodeList && $timeNodes->length > 0) {
            $node = $timeNodes->item(0);
            $datetime = $node instanceof \DOMElement ? $node->getAttribute('datetime') : '';
            if ('' !== $datetime) {
                return $this->parseDate($datetime);
            }
        }

        return null;
    }

    private function getMetaContent(\DOMXPath $xpath, string $value, string $attribute): string
    {
        if (str_contains($value, '"') || str_contains($value, "'") || str_contains($attribute, '"')) {
            return '';
        }

        $nodes = $xpath->query(\sprintf('//meta[@%s="%s"]/@content', $attribute, $value));
        if ($nodes instanceof \DOMNodeList && $nodes->length > 0) {
            $node = $nodes->item(0);

            return $node instanceof \DOMNode ? trim($node->nodeValue ?? '') : '';
        }

        return '';
    }

    private function normalizeLanguage(string $lang): string
    {
        $lang = strtolower(trim($lang));
        $lang = explode('-', $lang)[0];
        $lang = explode('_', $lang)[0];

        return match ($lang) {
            'en', 'fr' => $lang,
            default => self::DEFAULT_LANGUAGE,
        };
    }

    private function parseDate(string $dateString): ?\DateTimeImmutable
    {
        $dateString = trim($dateString);
        if ('' === $dateString) {
            return null;
        }

        // Unix timestamp (bare number)
        if (preg_match('/^\d{10,13}$/', $dateString)) {
            $timestamp = (int) substr($dateString, 0, 10); // Normalize ms to seconds

            return new \DateTimeImmutable('@' . $timestamp);
        }

        // ISO 8601 and common formats handled natively by DateTimeImmutable
        try {
            $date = new \DateTimeImmutable($dateString);

            // Sanity check: reject dates that PHP "parsed" into nonsense (e.g. "not-a-date" → today)
            if (false !== strtotime($dateString)) {
                return $date;
            }
        } catch (\Exception) {
            // Fall through to locale-aware parsing
        }

        // Localized date formats via IntlDateFormatter
        return $this->parseLocalizedDate($dateString);
    }

    private function parseLocalizedDate(string $dateString): ?\DateTimeImmutable
    {
        $locales = ['fr_FR', 'en_US', 'de_DE', 'es_ES', 'it_IT', 'pt_BR', 'ja_JP', 'zh_CN', 'ko_KR', 'ar_SA'];
        $formats = [\IntlDateFormatter::LONG, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT];

        foreach ($locales as $locale) {
            foreach ($formats as $format) {
                $formatter = new \IntlDateFormatter($locale, $format, \IntlDateFormatter::NONE, 'UTC');
                $timestamp = $formatter->parse($dateString);
                if (false !== $timestamp && \is_int($timestamp)) {
                    return new \DateTimeImmutable('@' . $timestamp)->setTimezone(new \DateTimeZone('UTC'));
                }
            }
        }

        return null;
    }

    /**
     * Extract the main image URL from the page.
     * Priority: og:image → twitter:image → first <img> with meaningful src in <article>/<main>/<body>.
     */
    private function extractImageUrl(\DOMXPath $xpath): ?string
    {
        // Open Graph image (most reliable for articles)
        $ogImage = $this->getMetaContent($xpath, 'og:image', 'property');
        if ('' !== $ogImage && $this->isAbsoluteUrl($ogImage)) {
            return $ogImage;
        }

        // Twitter card image
        $twitterImage = $this->getMetaContent($xpath, 'twitter:image', 'name');
        if ('' !== $twitterImage && $this->isAbsoluteUrl($twitterImage)) {
            return $twitterImage;
        }

        // First meaningful <img> in content areas
        foreach (['//article//img[@src]', '//main//img[@src]', '//body//img[@src]'] as $query) {
            $images = $xpath->query($query);
            if (!$images instanceof \DOMNodeList) {
                continue;
            }

            for ($i = 0; $i < $images->length; ++$i) {
                $node = $images->item($i);
                if (!$node instanceof \DOMElement) {
                    continue;
                }

                $src = $node->getAttribute('src');
                if ($this->isAbsoluteUrl($src) && !$this->isTrackingPixelOrIcon($node)) {
                    return $src;
                }
            }
        }

        return null;
    }

    private function isAbsoluteUrl(string $url): bool
    {
        if ('' === $url) {
            return false;
        }

        // Must be http(s) or protocol-relative
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://') && !str_starts_with($url, '//')) {
            return false;
        }

        // Reject data URIs
        if (str_starts_with($url, 'data:')) {
            return false;
        }

        return true;
    }

    private function isTrackingPixelOrIcon(\DOMElement $img): bool
    {
        // Check dimensions — tracking pixels are typically 1x1
        $width = $img->getAttribute('width');
        $height = $img->getAttribute('height');
        if (('' !== $width && (int) $width <= 2) || ('' !== $height && (int) $height <= 2)) {
            return true;
        }

        // Check common icon/logo/avatar patterns in class or src
        $src = strtolower($img->getAttribute('src'));
        $class = strtolower($img->getAttribute('class'));
        $iconPatterns = ['favicon', 'logo', 'avatar', 'icon', 'pixel', 'tracker', 'beacon', 'spacer'];

        foreach ($iconPatterns as $pattern) {
            if (str_contains($src, $pattern) || str_contains($class, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract canonical URL from <link rel="canonical"> or og:url.
     */
    /**
     * Extract author from meta tags and structured data.
     * Priority: meta author → article:author → schema.org author → byline class.
     */
    private function extractAuthor(\DOMXPath $xpath): ?string
    {
        // <meta name="author" content="...">
        $author = $this->getMetaContent($xpath, 'author', 'name');
        if ('' !== $author) {
            return $author;
        }

        // <meta property="article:author" content="...">
        $articleAuthor = $this->getMetaContent($xpath, 'article:author', 'property');
        if ('' !== $articleAuthor) {
            // Sometimes this is a URL (e.g. Facebook profile) — skip URLs
            if (!str_starts_with($articleAuthor, 'http')) {
                return $articleAuthor;
            }
        }

        // Schema.org: <span itemprop="author">...</span> or <*itemprop="name"> inside <*itemprop="author">
        $schemaAuthor = $xpath->query('//*[@itemprop="author"]');
        if ($schemaAuthor instanceof \DOMNodeList && $schemaAuthor->length > 0) {
            $node = $schemaAuthor->item(0);
            if ($node instanceof \DOMNode) {
                $nameNode = $xpath->query('.//*[@itemprop="name"]', $node);
                if ($nameNode instanceof \DOMNodeList && $nameNode->length > 0) {
                    $name = $nameNode->item(0);
                    $text = $name instanceof \DOMNode ? trim($name->textContent) : '';
                    if ('' !== $text) {
                        return $text;
                    }
                }

                $text = trim($node->textContent);
                if ('' !== $text && mb_strlen($text) < 100) {
                    return $text;
                }
            }
        }

        // Common CSS class patterns for bylines
        foreach (['//*[contains(@class,"byline")]', '//*[contains(@class,"author")]', '//*[@rel="author"]'] as $query) {
            $nodes = $xpath->query($query);
            if ($nodes instanceof \DOMNodeList && $nodes->length > 0) {
                $node = $nodes->item(0);
                $text = $node instanceof \DOMNode ? trim($node->textContent) : '';
                // Avoid grabbing entire sections — author names are short
                if ('' !== $text && mb_strlen($text) < 100) {
                    return $text;
                }
            }
        }

        return null;
    }

    /**
     * Extract site/publisher name.
     * Priority: og:site_name → application-name → schema.org publisher.
     */
    private function extractSiteName(\DOMXPath $xpath): ?string
    {
        $ogSiteName = $this->getMetaContent($xpath, 'og:site_name', 'property');
        if ('' !== $ogSiteName) {
            return $ogSiteName;
        }

        $appName = $this->getMetaContent($xpath, 'application-name', 'name');
        if ('' !== $appName) {
            return $appName;
        }

        // Schema.org publisher
        $publisher = $xpath->query('//*[@itemprop="publisher"]//*[@itemprop="name"]');
        if ($publisher instanceof \DOMNodeList && $publisher->length > 0) {
            $node = $publisher->item(0);
            $text = $node instanceof \DOMNode ? trim($node->textContent) : '';
            if ('' !== $text) {
                return $text;
            }
        }

        return null;
    }

    private function extractCanonicalUrl(\DOMXPath $xpath): ?string
    {
        // <link rel="canonical" href="..."> — authoritative
        $canonical = $xpath->query('//link[@rel="canonical"]/@href');
        if ($canonical instanceof \DOMNodeList && $canonical->length > 0) {
            $node = $canonical->item(0);
            $url = $node instanceof \DOMNode ? trim($node->nodeValue ?? '') : '';
            if ('' !== $url && $this->isAbsoluteUrl($url)) {
                return $url;
            }
        }

        // og:url — fallback
        $ogUrl = $this->getMetaContent($xpath, 'og:url', 'property');
        if ('' !== $ogUrl && $this->isAbsoluteUrl($ogUrl)) {
            return $ogUrl;
        }

        return null;
    }

    /**
     * Determine the base URL for resolving relative paths.
     * Priority: <base href> → canonical URL → source URL.
     */
    private function resolveBaseUrl(\DOMXPath $xpath, ?string $sourceUrl): ?string
    {
        $baseHref = $xpath->query('//base/@href');
        if ($baseHref instanceof \DOMNodeList && $baseHref->length > 0) {
            $node = $baseHref->item(0);
            $href = $node instanceof \DOMNode ? trim($node->nodeValue ?? '') : '';
            if ('' !== $href && $this->isAbsoluteUrl($href)) {
                return $href;
            }
        }

        return $sourceUrl;
    }

    /**
     * Resolve relative URLs (src, href, srcset) in HTML content to absolute URLs.
     */
    private function resolveRelativeUrls(string $html, string $baseUrl): string
    {
        $baseComponents = parse_url($baseUrl);
        if (false === $baseComponents || !isset($baseComponents['scheme'], $baseComponents['host'])) {
            return $html;
        }

        $origin = $baseComponents['scheme'] . '://' . $baseComponents['host'];
        if (isset($baseComponents['port'])) {
            $origin .= ':' . $baseComponents['port'];
        }

        // Resolve src="/path" and href="/path" (protocol-relative and root-relative)
        $html = (string) preg_replace_callback(
            '/(src|href|poster)=(["\'])((?:\/[^"\']*|\.\.?\/[^"\']*))\\2/i',
            fn (array $matches) => $matches[1] . '=' . $matches[2] . $this->makeAbsoluteUrl(
                $matches[3],
                $baseUrl
            ) . $matches[2],
            $html,
        );

        // Resolve srcset with relative URLs
        $html = (string) preg_replace_callback(
            '/srcset=(["\'])([^"\']+)\\1/i',
            function (array $matches) use ($baseUrl): string {
                $srcset = (string) preg_replace_callback(
                    '/(\S+)(\s+\d+[wx])/i',
                    fn (array $m) => ($this->isAbsoluteUrl($m[1]) ? $m[1] : $this->makeAbsoluteUrl(
                        $m[1],
                        $baseUrl
                    )) . $m[2],
                    $matches[2],
                );

                return 'srcset=' . $matches[1] . $srcset . $matches[1];
            },
            $html,
        );

        return $html;
    }

    private function makeAbsoluteUrl(string $relativeUrl, string $baseUrl): string
    {
        // Protocol-relative
        if (str_starts_with($relativeUrl, '//')) {
            $scheme = parse_url($baseUrl, \PHP_URL_SCHEME) ?: 'https';

            return $scheme . ':' . $relativeUrl;
        }

        $baseComponents = parse_url($baseUrl);
        if (false === $baseComponents || !isset($baseComponents['scheme'], $baseComponents['host'])) {
            return $relativeUrl;
        }

        $origin = $baseComponents['scheme'] . '://' . $baseComponents['host'];
        if (isset($baseComponents['port'])) {
            $origin .= ':' . $baseComponents['port'];
        }

        // Root-relative: /path/to/image
        if (str_starts_with($relativeUrl, '/')) {
            return $origin . $relativeUrl;
        }

        // Relative: ../path or path/to/image
        $basePath = $baseComponents['path'] ?? '/';
        $basePath = substr($basePath, 0, (int) strrpos($basePath, '/') + 1);

        return $origin . $basePath . $relativeUrl;
    }

    private function stripToText(\DOMXPath $xpath): string
    {
        $body = $xpath->query('//body');
        if ($body instanceof \DOMNodeList && $body->length > 0) {
            $node = $body->item(0);

            return $node instanceof \DOMNode ? trim($node->textContent) : '';
        }

        return '';
    }

    /**
     * Detect encoding from HTML meta tags and convert to UTF-8 if needed.
     * Supports <meta charset="...">, <meta http-equiv="Content-Type" content="...; charset=...">,
     * and BOM detection.
     */
    private function ensureUtf8(string $html): string
    {
        // Strip BOM if present
        if (str_starts_with($html, "\xEF\xBB\xBF")) {
            $html = substr($html, 3);
        } elseif (str_starts_with($html, "\xFF\xFE") || str_starts_with($html, "\xFE\xFF")) {
            $html = substr($html, 2);
        }

        if (mb_check_encoding($html, 'UTF-8') && !$this->hasNonUtf8Declaration($html)) {
            return $html;
        }

        $encoding = $this->detectEncoding($html);

        if ('UTF-8' === strtoupper($encoding)) {
            return $html;
        }

        $converted = mb_convert_encoding($html, 'UTF-8', $encoding);
        if (false === $converted) {
            return $html;
        }

        // Replace the declared encoding in the HTML with UTF-8
        $converted = (string) preg_replace(
            '/(<meta[^>]+charset\s*=\s*["\']?)' . preg_quote($encoding, '/') . '/i',
            '${1}UTF-8',
            $converted,
        );

        return $converted;
    }

    private function detectEncoding(string $html): string
    {
        // BOM detection and stripping
        if (str_starts_with($html, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }

        if (str_starts_with($html, "\xFF\xFE")) {
            return 'UTF-16LE';
        }
        if (str_starts_with($html, "\xFE\xFF")) {
            return 'UTF-16BE';
        }

        // <meta charset="...">
        if (preg_match('/<meta[^>]+charset\s*=\s*["\']?\s*([a-zA-Z0-9\-_]+)/i', $html, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        // <meta http-equiv="Content-Type" content="text/html; charset=...">
        if (preg_match('/<meta[^>]+content\s*=\s*["\'][^"\']*charset=([a-zA-Z0-9\-_]+)/i', $html, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        // Fallback: let mbstring detect
        $detected = mb_detect_encoding(
            $html,
            ['UTF-8', 'ISO-8859-1', 'ISO-8859-15', 'Windows-1252', 'Shift_JIS', 'EUC-JP', 'EUC-KR', 'GB2312', 'Big5'],
            true
        );

        return $detected ?: 'UTF-8';
    }

    private function hasNonUtf8Declaration(string $html): bool
    {
        if (preg_match('/<meta[^>]+charset\s*=\s*["\']?\s*([a-zA-Z0-9\-_]+)/i', $html, $matches)) {
            return 'UTF-8' !== strtoupper(trim($matches[1]));
        }

        if (preg_match('/<meta[^>]+content\s*=\s*["\'][^"\']*charset=([a-zA-Z0-9\-_]+)/i', $html, $matches)) {
            return 'UTF-8' !== strtoupper(trim($matches[1]));
        }

        return false;
    }
}
