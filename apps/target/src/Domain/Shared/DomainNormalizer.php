<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Pure domain helper for URL/host normalization shared by all quality processors.
 *
 * Centralizes:
 *   - host extraction and lowercasing
 *   - `www.` stripping
 *   - IDN/Punycode conversion (via `idn_to_ascii`)
 *   - registrable root domain extraction (Public Suffix–aware for the
 *     common 2-level ccTLDs encountered in production data)
 *
 * The eTLD list below is intentionally short. It is not a substitute for
 * the full Public Suffix List, but covers the ccTLDs we actually
 * encounter in collected documents. Unknown TLDs fall back to the
 * naive `last-two-labels` rule.
 */
class DomainNormalizer
{
    /**
     * Two-label public suffixes that must be treated as a single eTLD.
     * Source: Mozilla Public Suffix List, filtered to ccTLDs and common patterns.
     *
     * @var list<string>
     */
    private const array MULTI_LABEL_SUFFIXES = [
        'co.uk', 'org.uk', 'gov.uk', 'ac.uk', 'me.uk', 'net.uk', 'sch.uk',
        'com.au', 'net.au', 'org.au', 'edu.au', 'gov.au',
        'co.nz', 'net.nz', 'org.nz', 'govt.nz', 'school.nz',
        'co.jp', 'or.jp', 'ne.jp', 'ac.jp', 'go.jp', 'lg.jp',
        'com.br', 'net.br', 'org.br', 'gov.br', 'edu.br',
        'com.mx', 'gob.mx', 'edu.mx',
        'com.cn', 'net.cn', 'org.cn', 'gov.cn', 'edu.cn',
        'co.in', 'net.in', 'org.in', 'gov.in', 'ac.in',
        'co.za', 'net.za', 'org.za', 'gov.za', 'ac.za',
        'com.tr', 'net.tr', 'org.tr', 'gov.tr', 'edu.tr',
        'com.sg', 'edu.sg', 'gov.sg',
        'com.hk', 'gov.hk', 'edu.hk',
        'gouv.fr', 'asso.fr',
        'gov.it', 'edu.it',
        'gov.pl', 'com.pl',
        'co.kr', 'go.kr', 'or.kr',
        'gov.tw', 'edu.tw',
    ];

    /**
     * Extracts and normalizes the host from a URL, returning the registrable
     * root domain. Returns null when the URL is malformed or has no host.
     */
    public static function rootFromUrl(string $url): ?string
    {
        $host = parse_url($url, \PHP_URL_HOST);
        if (!\is_string($host) || '' === $host) {
            return null;
        }

        return self::root($host);
    }

    /**
     * Normalizes and returns the registrable root domain for a bare host name.
     * Returns null when the host is empty or cannot be IDN-encoded.
     */
    public static function root(string $host): ?string
    {
        $normalized = self::normalizeHost($host);
        if (null === $normalized) {
            return null;
        }

        foreach (self::MULTI_LABEL_SUFFIXES as $suffix) {
            $needle = '.' . $suffix;
            if (str_ends_with($normalized, $needle)) {
                $head = substr($normalized, 0, -\strlen($needle));
                $headParts = explode('.', $head);
                $last = end($headParts);

                return '' !== $last ? $last . $needle : $normalized;
            }
        }

        $parts = explode('.', $normalized);
        if (\count($parts) <= 2) {
            return $normalized;
        }

        return implode('.', \array_slice($parts, -2));
    }

    /**
     * Returns the last DNS label (TLD) of a host, lowercase and Punycode-encoded.
     * For multi-label public suffixes (e.g. `co.uk`), returns the full suffix.
     */
    public static function tld(string $host): ?string
    {
        $normalized = self::normalizeHost($host);
        if (null === $normalized) {
            return null;
        }

        foreach (self::MULTI_LABEL_SUFFIXES as $suffix) {
            if (str_ends_with($normalized, '.' . $suffix) || $normalized === $suffix) {
                return $suffix;
            }
        }

        $parts = explode('.', $normalized);
        $last = end($parts);

        return '' !== $last ? $last : null;
    }

    /**
     * Lowercases, strips `www.`, and Punycode-encodes if the host contains
     * non-ASCII characters. Returns null on any failure.
     */
    public static function normalizeHost(string $host): ?string
    {
        $host = trim($host);
        if ('' === $host) {
            return null;
        }

        $host = strtolower($host);
        $host = (string) preg_replace('/^www\./i', '', $host);

        if (1 === preg_match('/[^\x00-\x7f]/', $host)) {
            $ascii = idn_to_ascii($host, \IDNA_DEFAULT, \INTL_IDNA_VARIANT_UTS46);
            if (false === $ascii) {
                return null;
            }
            $host = $ascii;
        }

        return '' === $host ? null : $host;
    }
}
