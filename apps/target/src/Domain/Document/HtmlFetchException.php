<?php

declare(strict_types=1);

namespace App\Domain\Document;

class HtmlFetchException extends \RuntimeException
{
    public static function fetchFailed(string $url, string $reason): self
    {
        return new self(\sprintf('Failed to fetch URL "%s": %s', $url, $reason));
    }
}
