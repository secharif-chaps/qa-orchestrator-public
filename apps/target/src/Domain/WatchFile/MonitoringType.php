<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

enum MonitoringType: string
{
    case COMPETITIVE = 'competitive';
    case STRATEGIC = 'strategic';
    case COMMERCIAL = 'commercial';
    case TECHNOLOGICAL = 'technological';
    case REGULATORY = 'regulatory';
    case REPUTATIONAL = 'reputational';
}
