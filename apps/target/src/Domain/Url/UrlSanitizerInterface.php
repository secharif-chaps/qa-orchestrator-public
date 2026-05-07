<?php

declare(strict_types=1);

namespace App\Domain\Url;

/**
 * SSRF-aware URL gatekeeper used by every code path that fetches a
 * caller-provided URL (CLI manual ingestion, API document creation,
 * future provider-side fetches).
 *
 * Two responsibilities:
 *
 * - {@see assertSafePublicUrl()} — reject URLs that target the local
 *   network or use unsupported schemes. Without this, `http://localhost/admin`,
 *   `http://10.0.0.1/`, `file:///etc/passwd` and cloud-metadata endpoints
 *   (`http://169.254.169.254/`) reach the HTTP client and either succeed
 *   (information disclosure) or fail noisily (DoS amplification).
 *
 * - {@see redactCredentials()} — strip the `userinfo` part of a URL before
 *   logging or echoing it back to the operator. `https://user:pass@host/path`
 *   becomes `https://host/path`. Same intent as the comment in
 *   {@see App\Domain\Document\CanonicalUrlExtractor} (~line 321) but
 *   reusable across logger sites.
 *
 * Implementations are stateless and side-effect free except for the DNS
 * resolution `assertSafePublicUrl` performs to map a hostname to its IP.
 */
interface UrlSanitizerInterface
{
    public function assertSafePublicUrl(string $url): void;

    /**
     * Return the URL with `user:pass@` stripped if any. Malformed input is
     * returned verbatim (callers feeding URLs into a logger don't want
     * exceptions on bad data — they want the sanitised string or, failing
     * that, the original).
     */
    public function redactCredentials(string $url): string;
}
