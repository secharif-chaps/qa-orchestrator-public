<?php

declare(strict_types=1);

namespace App\Domain\Url\Exception;

/**
 * Thrown by {@see App\Domain\Url\UrlSanitizerInterface::assertSafePublicUrl()}
 * when a URL targets the local network, an unsupported scheme, or any other
 * destination considered unsafe for outbound HTTP fetches.
 *
 * Callers map this to:
 * - `HtmlFetchException` when raised inside a fetcher implementation
 * - a 4xx HTTP response in API/CLI processors so the operator gets a clear
 *   "this URL is not allowed" message instead of an internal 500.
 */
class UnsafeUrlException extends \DomainException
{
}
