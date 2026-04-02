<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Workflow;

readonly class ProcessWatchFileClassificationResultAction
{
    /**
     * @param array<string, mixed> $classification
     */
    public function __construct(
        public string $watchFileId,
        public string $conversationId,
        public string $messageId,
        public array $classification,
    ) {
    }
}
