<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentQuality;

use App\Domain\DocumentQuality\AdblockListEntry;
use App\Domain\DocumentQuality\AdblockListSourceInterface;
use App\Domain\DocumentQuality\Exception\AdblockSourceUnavailableException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Template method base for HTTP-backed adblock list sources that produce
 * one entry per text line. Subclasses only declare their endpoint, name
 * and line-parsing strategy.
 */
#[AutoconfigureTag('app.adblock_list_source')]
abstract class AbstractHttpLineAdblockListSource implements AdblockListSourceInterface
{
    private const int DEFAULT_TIMEOUT_SECONDS = 60;
    private const int DEFAULT_MAX_DURATION_SECONDS = 120;

    public function __construct(
        protected readonly HttpClientInterface $httpClient,
        protected readonly ?LoggerInterface $logger = null,
    ) {
    }

    abstract public function name(): string;

    abstract protected function listUrl(): string;

    abstract protected function parseLine(string $line): ?AdblockListEntry;

    final public function fetchEntries(): iterable
    {
        $body = $this->fetchBody();
        $lines = preg_split('/\r?\n/', $body) ?: [];

        foreach ($lines as $line) {
            $entry = $this->parseLine($line);
            if (null !== $entry) {
                yield $entry;
            }
        }
    }

    private function fetchBody(): string
    {
        $url = $this->listUrl();

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => self::DEFAULT_TIMEOUT_SECONDS,
                // Hard ceiling on the total download budget — `timeout` alone
                // is per-chunk idle; a slowloris-style server could keep the
                // connection alive indefinitely without hitting it.
                'max_duration' => self::DEFAULT_MAX_DURATION_SECONDS,
            ]);

            return $response->getContent();
        } catch (HttpExceptionInterface $exception) {
            $this->logger?->warning('Adblock list source unavailable', [
                'source' => $this->name(),
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);

            throw new AdblockSourceUnavailableException($this->name(), $exception);
        }
    }
}
