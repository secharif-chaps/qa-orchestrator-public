<?php

declare(strict_types=1);

namespace App\Application\WatchFile\StrategicQuestion;

use App\Application\SyncActionInterface;
use App\Domain\Chat\Message;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\StrategicQuestion;
use App\Domain\WatchFile\WatchFile;

readonly class AddStrategicQuestionAction implements SyncActionInterface
{
    public function __construct(
        public string $watchFileId,
        public string $questionFR,
        public string $questionEN,
        public string $contextFR,
        public string $contextEN,
        public MonitoringType $monitoringDimension,
        public int $priority,
        public string $expectedOutputType,
        public ?string $messageId = null,
    ) {
    }

    public function toEntity(WatchFile $watchFile, ?Message $addedByMessage = null): StrategicQuestion
    {
        return new StrategicQuestion(
            question: new TranslatedText($this->questionFR, $this->questionEN),
            context: new TranslatedText($this->contextFR, $this->contextEN),
            monitoringDimension: $this->monitoringDimension,
            priority: $this->priority,
            expectedOutputType: $this->expectedOutputType,
            watchFile: $watchFile,
            addedByMessage: $addedByMessage,
        );
    }
}
