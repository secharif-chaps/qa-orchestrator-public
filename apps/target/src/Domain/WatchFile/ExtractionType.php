<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

enum ExtractionType: string
{
    case ACTOR = 'actor';
    case SOURCE = 'source';
    case TOPIC = 'topic';
}
