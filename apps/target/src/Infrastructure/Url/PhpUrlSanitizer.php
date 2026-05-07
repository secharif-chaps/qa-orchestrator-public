<?php

declare(strict_types=1);

namespace App\Infrastructure\Url;

use App\Domain\Url\Exception\UnsafeUrlException;
use App\Domain\Url\UrlSanitizerInterface;

/**
 * `parse_url` + `gethostbyname`-based implementation of
 * {@see UrlSanitizerInterface}.
 *
 * Trade-offs vs a real network-aware library:
 * - DNS resolution uses PHP's blocking `gethostbyname` (5s default timeout).
 *   A mis-configured resolver could slow down the fetch path; for now we
 *   accept this as the entire web ingestion path is already synchronous
 *   and bounded by the cloudflare browser-render timeout downstream.
 * - We check the resolved A record only — multi-A or AAAA spoofing
 *   ("DNS rebinding") is not addressed at this layer; the HTTP client
 *   would still resolve at connect time, possibly to a different IP.
 *   Mitigations belong in the HTTP client (pinned resolver, hostname
 *   IP allowlist) rather than here. This is a defence-in-depth check.
 */
readonly class PhpUrlSanitizer implements UrlSanitizerInterface
{
    private const array ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * Hostname-level denylist — checked before DNS resolution so a poorly
     * configured DNS doesn't bypass the filter. Plain literals only, no
     * subdomain matching.
     */
    private const array DENIED_HOSTS = [
        'localhost',
        '0.0.0.0',
        // Cloud-metadata service hostnames.
        'metadata.google.internal',
        'metadata.aws.amazon.com',
        'metadata.azure.com',
    ];

    /**
     * IPv4 ranges (CIDR notation). Any host whose A record matches one of
     * these is rejected.
     */
    private const array DENIED_IPV4_RANGES = [
        // Loopback (127.0.0.0/8).
        ['127.0.0.0', 8],
        // RFC 1918 private ranges.
        ['10.0.0.0', 8],
        ['172.16.0.0', 12],
        ['192.168.0.0', 16],
        // Link-local (169.254.0.0/16) — also covers cloud metadata
        // endpoints like 169.254.169.254 (AWS, GCP, OpenStack, Hetzner).
        ['169.254.0.0', 16],
        // CGNAT (100.64.0.0/10).
        ['100.64.0.0', 10],
        // "This network" (0.0.0.0/8) — already partially covered by
        // DENIED_HOSTS but a literal IP would otherwise slip past.
        ['0.0.0.0', 8],
    ];

    public function assertSafePublicUrl(string $url): void
    {
        $parts = parse_url($url);
        if (false === $parts) {
            throw new UnsafeUrlException(\sprintf('URL is malformed: "%s".', $url));
        }

        $scheme = isset($parts['scheme']) ? mb_strtolower($parts['scheme']) : null;
        if (null === $scheme || !\in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new UnsafeUrlException(\sprintf(
                'URL scheme "%s" is not allowed (only http/https).',
                $scheme ?? '',
            ));
        }

        $host = isset($parts['host']) ? mb_strtolower($parts['host']) : '';
        if ('' === $host) {
            throw new UnsafeUrlException(\sprintf('URL has no host: "%s".', $url));
        }

        if (\in_array($host, self::DENIED_HOSTS, true)) {
            throw new UnsafeUrlException(\sprintf('URL targets a denied host: "%s".', $host));
        }

        // Strip optional brackets around IPv6 literals.
        $hostForResolution = trim($host, '[]');
        $ip = $this->resolveIp($hostForResolution);
        if (null !== $ip && $this->isPrivateIpv4($ip)) {
            throw new UnsafeUrlException(\sprintf(
                'URL host "%s" resolves to a private/loopback/link-local address (%s).',
                $host,
                $ip,
            ));
        }
    }

    public function redactCredentials(string $url): string
    {
        $parts = parse_url($url);
        if (false === $parts || (!isset($parts['user']) && !isset($parts['pass']))) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return \sprintf('%s://%s%s%s%s%s', $scheme, $host, $port, $path, $query, $fragment);
    }

    private function resolveIp(string $host): ?string
    {
        // If the host is already a valid IPv4 literal, skip DNS.
        if (false !== filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            return $host;
        }

        // IPv6 literals: we don't currently cover IPv6 ranges. Skip the
        // DNS lookup and let DNS-level routing decide. Defence-in-depth
        // against IPv6 SSRF should be added once the platform routinely
        // operates on dual-stack networks.
        if (false !== filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
            return null;
        }

        $resolved = gethostbyname($host);
        if ($resolved === $host) {
            // gethostbyname returns the input unchanged on failure.
            return null;
        }

        return $resolved;
    }

    private function isPrivateIpv4(string $ip): bool
    {
        if (false === filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            return false;
        }

        $ipLong = ip2long($ip);
        if (false === $ipLong) {
            return false;
        }

        foreach (self::DENIED_IPV4_RANGES as [$rangeIp, $bits]) {
            $rangeLong = ip2long($rangeIp);
            if (false === $rangeLong) {
                continue;
            }
            $mask = -1 << (32 - $bits);
            if (($ipLong & $mask) === ($rangeLong & $mask)) {
                return true;
            }
        }

        return false;
    }
}
