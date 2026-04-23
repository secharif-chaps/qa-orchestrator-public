<?php

declare(strict_types=1);

namespace App\Domain\Collect;

use App\Domain\Document\Document;

interface ApifyDocumentNormalizerInterface
{
    public function supports(string $actorType): bool;

    /**
     * Normalize a raw Apify dataset item into a Document.
     *
     * @param array<string, mixed> $item
     *
     * @return Document|null null if the item should be skipped
     */
    public function normalize(array $item, NormalizerContext $context): ?Document;
}
