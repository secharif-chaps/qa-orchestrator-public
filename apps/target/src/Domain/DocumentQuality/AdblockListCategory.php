<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

enum AdblockListCategory: string
{
    case ADS = 'ads';
    case MALWARE = 'malware';
    case FAKE_NEWS = 'fake_news';
    case TRACKING = 'tracking';
    case UNCLASSIFIED = 'unclassified';
}
