<?php

declare(strict_types=1);

namespace App\Domain\WatchFile\Event;

use App\Domain\WatchFile\StrategicQuestion;
use App\Domain\WatchFile\WatchFile;
use Symfony\Contracts\EventDispatcher\Event;

class QuestionsGeneratedEvent extends Event
{
    public const string NAME = 'watchfile.questions_generated';

    public function __construct(
        public readonly WatchFile $watchFile,
        /**
         * @var list<StrategicQuestion>
         */
        public readonly array $strategicQuestions,
    ) {
    }
}
