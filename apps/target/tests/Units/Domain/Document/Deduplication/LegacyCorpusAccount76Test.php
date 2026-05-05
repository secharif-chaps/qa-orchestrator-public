<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Deduplication;

class LegacyCorpusAccount76Test extends AbstractLegacyCorpusDeduplicationTestCase
{
    protected static function accountId(): string
    {
        return '76';
    }
}
