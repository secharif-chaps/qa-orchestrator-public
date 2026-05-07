<?php

declare(strict_types=1);

namespace App\Application\Collect\Web;

use App\Application\Collect\Web\Exception\InvalidWebCollectConfigException;

/**
 * Typed view over the `configuration` payload that the inline `web` provider
 * expects on a {@see App\Domain\Collect\CollectTask}.
 *
 * Replaces the stringly-typed `array_filter([...])` blocks that lived in
 * `CreateDocumentCommand`, `CreateDocumentProcessor`, `WebProviderGateway`
 * and `FetchWebUrlHandler` — same data, single source of truth, single
 * place to validate the "url XOR raw_html" invariant.
 *
 * Stored shape (`CollectTask::$configuration`):
 *
 *     [
 *       'url' => '…',                    // optional
 *       'raw_html' => '…',               // optional, exclusive with url
 *       'title' => '…',                  // optional override
 *       'excerpt' => '…',                // optional override
 *       '_sync_chain' => true,           // present only when CLI/API
 *                                        // wants the chain to keep running
 *                                        // inline through IngestDocumentAction
 *     ]
 *
 * The leading underscore on `_sync_chain` is a deliberate marker for
 * "transport metadata, not user data" — providers and tooling should
 * never persist it back as part of any user-facing payload.
 */
readonly class WebCollectConfig
{
    public const string KEY_URL = 'url';
    public const string KEY_RAW_HTML = 'raw_html';
    public const string KEY_TITLE = 'title';
    public const string KEY_EXCERPT = 'excerpt';
    public const string KEY_SYNC_CHAIN = '_sync_chain';

    public function __construct(
        public ?string $url = null,
        public ?string $rawHtml = null,
        public ?string $titleOverride = null,
        public ?string $excerptOverride = null,
        public bool $syncChain = false,
    ) {
    }

    /**
     * Build a config from a free-form CollectTask configuration array.
     * Callers that read from the persisted CollectTask use this to recover
     * a typed view; type mismatches surface as a single domain exception.
     *
     * @param array<string, mixed> $configuration
     */
    public static function fromCollectTaskConfiguration(array $configuration): self
    {
        return new self(
            url: self::optionalString($configuration, self::KEY_URL),
            rawHtml: self::optionalString($configuration, self::KEY_RAW_HTML),
            titleOverride: self::optionalString($configuration, self::KEY_TITLE),
            excerptOverride: self::optionalString($configuration, self::KEY_EXCERPT),
            syncChain: (bool) ($configuration[self::KEY_SYNC_CHAIN] ?? false),
        );
    }

    /**
     * Throws when the config does not satisfy the invariant that the web
     * provider needs at least one of `url` or `raw_html` set to a non-empty
     * string. Caller-provided configs (CLI/API) are validated here too —
     * the gateway and the handler share the same gate.
     */
    public function validate(): void
    {
        $hasUrl = null !== $this->url && '' !== $this->url;
        $hasRawHtml = null !== $this->rawHtml && '' !== $this->rawHtml;

        if (!$hasUrl && !$hasRawHtml) {
            throw new InvalidWebCollectConfigException(
                'Web provider requires either "url" or "raw_html" in CollectTask configuration.',
            );
        }
    }

    /**
     * Serialise to the JSON-safe array shape stored on `CollectTask::$configuration`.
     * Null fields and `syncChain == false` are filtered out so the persisted
     * payload stays minimal and round-trips through `fromCollectTaskConfiguration()`
     * without re-introducing accidental keys.
     *
     * @return array<string, mixed>
     */
    public function toCollectTaskConfiguration(): array
    {
        $configuration = [];
        if (null !== $this->url) {
            $configuration[self::KEY_URL] = $this->url;
        }
        if (null !== $this->rawHtml) {
            $configuration[self::KEY_RAW_HTML] = $this->rawHtml;
        }
        if (null !== $this->titleOverride) {
            $configuration[self::KEY_TITLE] = $this->titleOverride;
        }
        if (null !== $this->excerptOverride) {
            $configuration[self::KEY_EXCERPT] = $this->excerptOverride;
        }
        if ($this->syncChain) {
            $configuration[self::KEY_SYNC_CHAIN] = true;
        }

        return $configuration;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private static function optionalString(array $configuration, string $key): ?string
    {
        if (!isset($configuration[$key])) {
            return null;
        }

        $value = $configuration[$key];
        if (!\is_string($value)) {
            throw new InvalidWebCollectConfigException(\sprintf(
                'CollectTask configuration key "%s" must be a string when present.',
                $key
            ), );
        }

        return $value;
    }
}
