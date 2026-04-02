<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Workflow\SearchQuery;

readonly class RunSearchQueryAction
{
    public function __construct(
        public string $strategicQuestionId,
    ) {
    }
}
