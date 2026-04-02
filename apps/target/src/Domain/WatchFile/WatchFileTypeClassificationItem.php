<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use App\Domain\Shared\TranslatedText;

readonly class WatchFileTypeClassificationItem
{
    public function __construct(
        public MonitoringType $type,
        public int $confidenceScore,
        public TranslatedText $justification,
    ) {
    }
}
