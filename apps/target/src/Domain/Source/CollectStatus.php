<?php

namespace App\Domain\Source;

enum CollectStatus: string
{
    case STOPPED = 'stopped';
    case RUNNING = 'running';
    case ERROR = 'error';
}
