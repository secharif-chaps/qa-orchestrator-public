<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentQuality;

use App\Domain\DocumentQuality\AdblockListCategory;
use App\Domain\DocumentQuality\AdblockListEntry;
use App\Domain\Shared\DomainNormalizer;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads StevenBlack's unified hosts list (https://github.com/StevenBlack/hosts).
 *
 * The default StevenBlack unified list covers ads, malware and fake news.
 * File format is the standard `/etc/hosts` syntax:
 *
 *     # Comment
 *     0.0.0.0 doubleclick.net
 *     0.0.0.0 ads.example.com
 */
class StevenBlackHostsSource extends AbstractHttpLineAdblockListSource
{
    private const string DEFAULT_URL = 'https://raw.githubusercontent.com/StevenBlack/hosts/master/hosts';
    private const string SOURCE_NAME = 'stevenblack';

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $listUrl = self::DEFAULT_URL,
        private readonly AdblockListCategory $category = AdblockListCategory::ADS,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($httpClient, $logger);
    }

    public function name(): string
    {
        return self::SOURCE_NAME;
    }

    protected function listUrl(): string
    {
        return $this->listUrl;
    }

    protected function parseLine(string $line): ?AdblockListEntry
    {
        $line = trim($line);
        if ('' === $line || str_starts_with($line, '#')) {
            return null;
        }

        $parts = preg_split('/\s+/', $line, 2);
        if (false === $parts || 2 !== \count($parts)) {
            return null;
        }

        [$address, $host] = $parts;
        if ('0.0.0.0' !== $address && '127.0.0.1' !== $address) {
            return null;
        }

        $host = (string) preg_replace('/[\s#].*$/', '', $host);

        // Single-label hosts like `localhost` cannot match any document URL
        // and would only pollute the consolidated HashSet.
        $normalized = DomainNormalizer::root($host);
        if (null === $normalized || !str_contains($normalized, '.')) {
            return null;
        }

        return new AdblockListEntry($normalized, $this->category);
    }
}
