<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Actor;

readonly class AddMultipleActorsAction
{
    /**
     * @param array<int, array{
     *   name?: mixed,
     *   type?: string,
     *   explanation?: array{fr: string, en: string},
     *   primaryDomain?: string,
     *   score?: float
     * }> $actors
     */
    public function __construct(
        public array $actors,
        public string $watchFileId,
    ) {
    }
}
