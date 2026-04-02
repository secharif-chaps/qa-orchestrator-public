<?php

declare(strict_types=1);

namespace App\Domain\Source;

/**
 * Utility service for domain normalization and matching.
 *
 * Handles domain comparison with support for:
 * - Case-insensitive matching
 * - WWW prefix normalization (www.example.com matches example.com)
 * - URL parsing to extract domain names
 */
readonly class DomainMatcher
{
    /**
     * Normalizes a domain name for comparison.
     *
     * This method:
     * - Converts the domain to lowercase
     * - Strips the "www." prefix if present
     * - Returns null for empty or invalid domains
     *
     * Examples:
     * - "Acme.Com" -> "acme.com"
     * - "www.acme.com" -> "acme.com"
     * - "acme.com" -> "acme.com"
     *
     * @param string|null $domain The domain to normalize
     *
     * @return string|null The normalized domain, or null if the input is empty or invalid
     */
    public function normalizeDomain(?string $domain): ?string
    {
        if (null === $domain || '' === trim($domain)) {
            return null;
        }

        $normalized = strtolower(trim($domain));

        // Strip www. prefix if present
        if (str_starts_with($normalized, 'www.')) {
            $normalized = substr($normalized, 4);
        }

        return '' === $normalized ? null : $normalized;
    }

    /**
     * Extracts the domain from a full URL.
     *
     * Uses PHP's parse_url() to extract the host component from a URL.
     * Handles various URL formats including:
     * - https://example.com/path
     * - http://www.example.com:8080/path?query=value
     * - example.com (if already a domain)
     *
     * @param string $url The URL to extract the domain from
     *
     * @return string|null The extracted domain, or null if extraction fails
     */
    public function extractDomainFromUrl(string $url): ?string
    {
        if ('' === trim($url)) {
            return null;
        }

        // If the URL doesn't have a protocol, parse_url might not work correctly
        // Try to add a protocol temporarily for parsing
        $urlToParse = $url;
        if (!preg_match('#^https?://#i', $url)) {
            $urlToParse = 'https://' . $url;
        }

        $parsed = parse_url($urlToParse);
        if (false === $parsed || !isset($parsed['host'])) {
            return null;
        }

        $host = $parsed['host'];

        // Basic validation: a valid domain should contain at least one dot
        // and not contain spaces or other obviously invalid characters
        if (str_contains($host, ' ') || (!str_contains($host, '.') && !filter_var($host, \FILTER_VALIDATE_IP))) {
            return null;
        }

        return $host;
    }

    /**
     * Checks if two domains match after normalization.
     *
     * Domains are considered matching if:
     * - They are identical after normalization (case-insensitive, www prefix stripped)
     * - Both domains are valid and non-empty
     *
     * Examples of matches:
     * - "acme.com" matches "Acme.Com"
     * - "www.acme.com" matches "acme.com"
     * - "acme.com" matches "www.acme.com"
     *
     * @param string|null $domain1 First domain to compare
     * @param string|null $domain2 Second domain to compare
     *
     * @return bool True if domains match, false otherwise
     */
    public function domainsMatch(?string $domain1, ?string $domain2): bool
    {
        $normalized1 = $this->normalizeDomain($domain1);
        $normalized2 = $this->normalizeDomain($domain2);

        // If either domain is null after normalization, they don't match
        if (null === $normalized1 || null === $normalized2) {
            return false;
        }

        return $normalized1 === $normalized2;
    }
}
