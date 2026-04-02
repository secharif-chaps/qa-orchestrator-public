<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Cloudflare;

class CloudflareFetchException extends \RuntimeException
{
    public static function fetchFailed(string $url, string $reason): self
    {
        return new self(\sprintf('Failed to fetch URL "%s" via Cloudflare Browser Rendering: %s', $url, $reason));
    }

    public static function emptyResult(string $url): self
    {
        return new self(\sprintf('Cloudflare Browser Rendering returned empty content for URL "%s"', $url));
    }
}
