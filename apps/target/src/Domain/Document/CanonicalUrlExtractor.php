<?php

declare(strict_types=1);

namespace App\Domain\Document;

/**
 * Resolve the canonical URL of a collected document.
 *
 * Cascade (first non-null wins):
 *   1. Provider-supplied canonical URL (already known by the upstream connector)
 *   2. <link rel="canonical"> in the raw HTML
 *   3. <meta property="og:url"> in the raw HTML
 *   4. The source URL of the document
 *
 * The resolved URL is then normalized so that two semantically-equivalent URLs
 * collapse to the same string (Stage 0 of the deduplication pipeline).
 */
class CanonicalUrlExtractor
{
    /** @var list<string> */
    private const array TRACKING_PARAM_PREFIXES = ['utm_'];

    /** @var list<string> */
    private const array TRACKING_PARAMS = [
        'fbclid',
        'gclid',
        'dclid',
        'msclkid',
        'mc_cid',
        'mc_eid',
        'yclid',
        '_ga',
        'ref',
        'source',
    ];

    /** @var list<string> */
    private const array ALLOWED_SCHEMES = ['http', 'https'];

    /** @var array<string, int> */
    private const array DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
    ];

    public function extract(?string $providerCanonicalUrl, ?string $rawHtml, ?string $sourceUrl): ?string
    {
        $resolved = $this->resolveFromProvider($providerCanonicalUrl, $sourceUrl)
            ?? $this->resolveFromHtml($rawHtml, $sourceUrl)
            ?? $this->resolveFromSource($sourceUrl);

        if (null === $resolved) {
            return null;
        }

        return $this->normalize($resolved);
    }

    /**
     * Normalise an already-absolute URL into the same canonical form
     * the extractor produces (lowercase scheme/host, IDN→Punycode, drop
     * default ports, drop tracking params, drop fragment, strip
     * trailing slash on non-root paths). Returns null when the input
     * is not a valid absolute http(s) URL.
     *
     * Used by callers that already hold a canonical-URL candidate (e.g.
     * an upstream collector that read it from a `<link rel="canonical">`)
     * and only want to align it with the index's stored form before
     * issuing a stage-0 lookup.
     *
     * @param string $url an absolute http(s) URL — relative URLs are
     *                    rejected (returns `null`) because this entry
     *                    point intentionally has no `$baseUrl` to resolve
     *                    them against
     */
    public function canonicalize(string $url): ?string
    {
        // baseUrl=null on purpose: this is the public canonicalize() path
        // for URLs that callers warrant to be already-absolute. Any
        // relative URL slipping through is rejected by `makeAbsoluteAndValidate`.
        $resolved = $this->makeAbsoluteAndValidate(trim($url), null);

        return null !== $resolved ? $this->normalize($resolved) : null;
    }

    private function resolveFromProvider(?string $providerUrl, ?string $sourceUrl): ?string
    {
        if (null === $providerUrl) {
            return null;
        }

        $trimmed = trim($providerUrl);
        if ('' === $trimmed) {
            return null;
        }

        return $this->makeAbsoluteAndValidate($trimmed, $sourceUrl);
    }

    private function resolveFromHtml(?string $rawHtml, ?string $sourceUrl): ?string
    {
        if (null === $rawHtml || '' === trim($rawHtml)) {
            return null;
        }

        $canonical = $this->extractLinkRelCanonical($rawHtml);
        if (null !== $canonical) {
            $resolved = $this->makeAbsoluteAndValidate($canonical, $sourceUrl);
            if (null !== $resolved) {
                return $resolved;
            }
        }

        $ogUrl = $this->extractOgUrl($rawHtml);
        if (null !== $ogUrl) {
            return $this->makeAbsoluteAndValidate($ogUrl, $sourceUrl);
        }

        return null;
    }

    private function resolveFromSource(?string $sourceUrl): ?string
    {
        if (null === $sourceUrl) {
            return null;
        }

        $trimmed = trim($sourceUrl);
        if ('' === $trimmed) {
            return null;
        }

        return $this->makeAbsoluteAndValidate($trimmed, null);
    }

    private function extractLinkRelCanonical(string $html): ?string
    {
        $cleaned = $this->stripHtmlComments($html);

        // Match <link ... rel="canonical" ... href="..."> with attributes in any order.
        // Two patterns to allow rel/href swap.
        $patterns = [
            '/<link\b[^>]*\brel\s*=\s*["\']canonical["\'][^>]*\bhref\s*=\s*["\']([^"\']*)["\'][^>]*>/i',
            '/<link\b[^>]*\bhref\s*=\s*["\']([^"\']*)["\'][^>]*\brel\s*=\s*["\']canonical["\'][^>]*>/i',
        ];

        foreach ($patterns as $pattern) {
            if (1 === preg_match($pattern, $cleaned, $matches)) {
                $href = trim($matches[1]);

                return '' !== $href ? $href : null;
            }
        }

        return null;
    }

    private function extractOgUrl(string $html): ?string
    {
        $cleaned = $this->stripHtmlComments($html);

        $patterns = [
            '/<meta\b[^>]*\bproperty\s*=\s*["\']og:url["\'][^>]*\bcontent\s*=\s*["\']([^"\']*)["\'][^>]*>/i',
            '/<meta\b[^>]*\bcontent\s*=\s*["\']([^"\']*)["\'][^>]*\bproperty\s*=\s*["\']og:url["\'][^>]*>/i',
        ];

        foreach ($patterns as $pattern) {
            if (1 === preg_match($pattern, $cleaned, $matches)) {
                $content = trim($matches[1]);

                return '' !== $content ? $content : null;
            }
        }

        return null;
    }

    /**
     * Drop HTML comments so that a `<link rel="canonical">` sitting inside
     * `<!-- ... -->` is not mistaken for a real canonical declaration.
     */
    private function stripHtmlComments(string $html): string
    {
        $stripped = preg_replace('/<!--.*?-->/s', '', $html);

        return null === $stripped ? $html : $stripped;
    }

    /**
     * Resolve relative or protocol-relative URLs against the source URL,
     * then validate that the resulting URL has an allowed scheme and a host.
     */
    private function makeAbsoluteAndValidate(string $url, ?string $baseUrl): ?string
    {
        $resolved = $this->resolveRelative($url, $baseUrl);
        if (null === $resolved) {
            return null;
        }

        $components = parse_url($resolved);
        if (false === $components || !isset($components['scheme'], $components['host'])) {
            return null;
        }

        $scheme = strtolower($components['scheme']);
        if (!\in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return null;
        }

        return $resolved;
    }

    private function resolveRelative(string $url, ?string $baseUrl): ?string
    {
        // Protocol-relative: //example.com/path
        if (str_starts_with($url, '//')) {
            $scheme = null !== $baseUrl ? parse_url($baseUrl, \PHP_URL_SCHEME) : null;
            if (!\is_string($scheme) || '' === $scheme) {
                $scheme = 'https';
            }

            return strtolower($scheme) . ':' . $url;
        }

        // Already absolute (has a scheme).
        if (1 === preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*:/', $url)) {
            return $url;
        }

        if (null === $baseUrl) {
            return null;
        }

        $baseComponents = parse_url($baseUrl);
        if (false === $baseComponents || !isset($baseComponents['scheme'], $baseComponents['host'])) {
            return null;
        }

        $origin = $baseComponents['scheme'] . '://' . $baseComponents['host'];
        if (isset($baseComponents['port'])) {
            $origin .= ':' . $baseComponents['port'];
        }

        // Root-relative: /path
        if (str_starts_with($url, '/')) {
            return $origin . $url;
        }

        // Path-relative: path or ../path
        $basePath = $baseComponents['path'] ?? '/';
        $lastSlash = strrpos($basePath, '/');
        $basePath = false === $lastSlash ? '/' : substr($basePath, 0, $lastSlash + 1);

        return $origin . $this->resolveDotSegments($basePath . $url);
    }

    /**
     * Resolve `.` and `..` path segments per RFC 3986 §5.2.4.
     * For absolute paths, `..` segments that would escape the root are dropped.
     */
    private function resolveDotSegments(string $path): string
    {
        if (!str_contains($path, '.')) {
            return $path;
        }

        $isAbsolute = str_starts_with($path, '/');
        $hasTrailingSlash = str_ends_with($path, '/');

        $segments = array_values(array_filter(
            explode('/', $path),
            static fn (string $segment): bool => '' !== $segment,
        ));

        $resolved = [];
        foreach ($segments as $segment) {
            if ('.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                if ([] !== $resolved && '..' !== end($resolved)) {
                    array_pop($resolved);

                    continue;
                }

                if (!$isAbsolute) {
                    $resolved[] = $segment;
                }

                continue;
            }

            $resolved[] = $segment;
        }

        $rebuilt = implode('/', $resolved);

        if ($isAbsolute) {
            $rebuilt = '/' . $rebuilt;
        }
        if ($hasTrailingSlash && '/' !== $rebuilt && !str_ends_with($rebuilt, '/')) {
            $rebuilt .= '/';
        }

        return $rebuilt;
    }

    private function normalize(string $url): string
    {
        $components = parse_url($url);
        if (false === $components || !isset($components['scheme'], $components['host'])) {
            return $url;
        }

        $scheme = mb_strtolower($components['scheme'], 'UTF-8');
        $host = $this->normalizeHost($components['host']);

        // userinfo (user:pass@) is intentionally dropped: it is never part of
        // a canonical document identifier and may leak credentials.
        $normalized = $scheme . '://' . $host;

        if (isset($components['port']) && !$this->isDefaultPort($scheme, $components['port'])) {
            $normalized .= ':' . $components['port'];
        }

        $path = $components['path'] ?? '';
        // Empty path is semantically equivalent to "/" (RFC 3986 §6.2.3).
        if ('' === $path) {
            $path = '/';
        }
        // Strip a trailing slash on non-root paths (`/foo/` ≡ `/foo`)
        // — Google Search-style canonicalisation. The root `/` is kept
        // because dropping it would also remove the path separator.
        // This collapses two URLs that resolve to the same resource on
        // every modern HTTP server (cf. example.com/about vs about/).
        if ('/' !== $path && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        $normalized .= $path;

        $query = $this->normalizeQuery($components['query'] ?? null);
        if (null !== $query) {
            $normalized .= '?' . $query;
        }

        // Fragments are intentionally dropped: they never identify a different document.
        return $normalized;
    }

    private function isDefaultPort(string $scheme, int $port): bool
    {
        return isset(self::DEFAULT_PORTS[$scheme]) && self::DEFAULT_PORTS[$scheme] === $port;
    }

    /**
     * Lowercase the host and convert internationalized domain names (IDN) to
     * their Punycode (ASCII) form so that the Unicode and ASCII spellings
     * collapse to the same canonical key. Falls back to a plain lowercase if
     * `idn_to_ascii` is unavailable or the host cannot be encoded.
     */
    private function normalizeHost(string $host): string
    {
        $lowered = mb_strtolower($host, 'UTF-8');

        // Pure ASCII (including already-Punycode `xn--…` labels) needs no IDN encoding.
        if (1 === preg_match('/^[\x00-\x7F]*$/', $lowered)) {
            return $lowered;
        }

        if (!\function_exists('idn_to_ascii')) {
            return $lowered;
        }

        $ascii = idn_to_ascii($lowered, \IDNA_DEFAULT, \INTL_IDNA_VARIANT_UTS46);
        if (false === $ascii || '' === $ascii) {
            return $lowered;
        }

        return $ascii;
    }

    private function normalizeQuery(?string $query): ?string
    {
        if (null === $query || '' === $query) {
            return null;
        }

        parse_str($query, $params);
        if ([] === $params) {
            return null;
        }

        $filtered = [];
        foreach ($params as $name => $value) {
            $key = (string) $name;
            if ($this->isTrackingParam($key)) {
                continue;
            }
            $filtered[$key] = $value;
        }

        if ([] === $filtered) {
            return null;
        }

        ksort($filtered);

        $rebuilt = http_build_query($filtered, '', '&', \PHP_QUERY_RFC3986);

        return '' !== $rebuilt ? $rebuilt : null;
    }

    private function isTrackingParam(string $name): bool
    {
        $lower = mb_strtolower($name, 'UTF-8');

        if (\in_array($lower, self::TRACKING_PARAMS, true)) {
            return true;
        }

        foreach (self::TRACKING_PARAM_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
