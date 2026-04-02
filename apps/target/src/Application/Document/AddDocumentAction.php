<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Domain\Document\Document;

final readonly class AddDocumentAction
{
    public function __construct(
        public string $collectTaskId,
        public Document $document,
    ) {
    }
}
