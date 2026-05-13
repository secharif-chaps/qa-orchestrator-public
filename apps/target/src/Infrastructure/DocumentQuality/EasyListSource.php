<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentQuality;

use App\Domain\DocumentQuality\AdblockListCategory;
use App\Domain\DocumentQuality\AdblockListEntry;
use App\Domain\Shared\DomainNormalizer;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads EasyList (https://easylist.to/), the most widely deployed adblock
 * filter list. Only "network" rules of the form `||domain^` are extracted —
 * cosmetic rules (`##`, `#@#`, …) and rules with option modifiers
 * (`||domain^$third-party`, `||domain^$image`, etc.) are skipped.
 *
 * Rationale for skipping option-bearing rules: they encode contextual
 * conditions that do not apply at the document-domain granularity we
 * score on. Including them would produce false positives.
 */
class EasyListSource extends AbstractHttpLineAdblockListSource
{
    private const string DEFAULT_URL = 'https://easylist.to/easylist/easylist.txt';
    private const string SOURCE_NAME = 'easylist';
    private const string NETWORK_RULE_PATTERN = '#^\|\|([a-z0-9.\-]+)\^$#i';

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
        if ('' === $line || str_starts_with($line, '!') || str_starts_with($line, '[')) {
            return null;
        }

        if (1 !== preg_match(self::NETWORK_RULE_PATTERN, $line, $matches)) {
            return null;
        }

        $normalized = DomainNormalizer::root($matches[1]);
        if (null === $normalized || !str_contains($normalized, '.')) {
            return null;
        }

        return new AdblockListEntry($normalized, $this->category);
    }
}
