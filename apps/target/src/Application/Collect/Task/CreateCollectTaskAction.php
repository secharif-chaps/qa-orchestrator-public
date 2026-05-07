<?php

declare(strict_types=1);

namespace App\Application\Collect\Task;

final readonly class CreateCollectTaskAction
{
    /**
     * @param array<string, mixed>|null $configuration Provider-specific overrides
     *                                                 stored on the resulting CollectTask before save. Apify/Bakus
     *                                                 dispatchers leave this `null`. The web/manual provider reads
     *                                                 `url` / `raw_html` / `title` / `excerpt` / `_sync_chain` keys.
     */
    public function __construct(
        public string $sourceId,
        public string $watchFileId,
        public bool $start = true,
        public ?array $configuration = null,
    ) {
    }
}
