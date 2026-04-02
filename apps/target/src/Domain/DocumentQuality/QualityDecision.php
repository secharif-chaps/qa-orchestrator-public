<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

enum QualityDecision: string
{
    case ACCEPTED = 'accepted';
    case REVIEW = 'review';
    case LOW_QUALITY = 'low_quality';
    case REJECTED = 'rejected';
}
