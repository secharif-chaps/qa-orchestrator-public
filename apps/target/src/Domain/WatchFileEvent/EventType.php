<?php

declare(strict_types=1);

namespace App\Domain\WatchFileEvent;

enum EventType: string
{
    case COMMERCIAL_BUSINESS = 'commercial_business';
    case FINANCIAL = 'financial';
    case ORGANIZATIONAL_HR = 'organizational_hr';
    case TECHNOLOGICAL_RD = 'technological_rd';
    case REGULATORY_POLITICAL = 'regulatory_political';
    case MARKET_COMPETITORS = 'market_competitors';
    case SOCIETAL_ENVIRONMENTAL = 'societal_environmental';
}
