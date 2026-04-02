<?php

declare(strict_types=1);

namespace App\Domain\WatchFile\Event;

use App\Domain\WatchFile\AnalysisResult;
use App\Domain\WatchFile\WatchFile;
use Symfony\Contracts\EventDispatcher\Event;

class NeedsAnalyzedEvent extends Event
{
    public const string NAME = 'watchfile.needs_analyzed';

    public function __construct(
        public readonly WatchFile $watchFile,
        public readonly AnalysisResult $analysisResult,
    ) {
    }
}
