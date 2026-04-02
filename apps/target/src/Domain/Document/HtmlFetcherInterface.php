<?php

declare(strict_types=1);

namespace App\Domain\Document;

interface HtmlFetcherInterface
{
    /**
     * Fetch fully rendered HTML content for the given URL.
     */
    public function fetch(string $url): string;
}
