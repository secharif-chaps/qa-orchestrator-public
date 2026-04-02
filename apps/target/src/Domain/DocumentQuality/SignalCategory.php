<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

enum SignalCategory: string
{
    case INFRASTRUCTURE_TRUST = 'infrastructure_trust';
    case CONTENT_QUALITY = 'content_quality';
    case SOURCE_CREDIBILITY = 'source_credibility';
    case METADATA = 'metadata';
}
