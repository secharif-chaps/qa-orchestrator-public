<?php

declare(strict_types=1);

namespace App\Application\WatchFile\StrategicQuestion;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Chat\MessageNotFoundException;
use App\Domain\WatchFile\StrategicQuestion;
use App\Domain\WatchFile\StrategicQuestionGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class AddStrategicQuestionHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private StrategicQuestionGatewayInterface $strategicQuestionGateway,
        private MessageGatewayInterface $messageGateway,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(AddStrategicQuestionAction $action): StrategicQuestion
    {
        $this->logger?->debug('Create strategic question action received', [
            'watch_file_id' => $action->watchFileId,
            'monitoring_dimension' => $action->monitoringDimension->value,
            'priority' => $action->priority,
        ]);

        $existingQuestion = $this->strategicQuestionGateway->findByQuestionAndWatchFile(
            $action->questionEN,
            $action->watchFileId,
        );

        if (null !== $existingQuestion) {
            $this->logger?->debug('Strategic question already exists, skipping creation', [
                'question_id' => $existingQuestion->getId(),
                'watch_file_id' => $action->watchFileId,
            ]);

            return $existingQuestion;
        }

        $watchFile = $this->getWatchFile($action->watchFileId);
        $message = $action->messageId ? $this->getMessage($action->messageId) : null;
        $strategicQuestion = $action->toEntity($watchFile, $message);

        $this->strategicQuestionGateway->save($strategicQuestion);

        $this->logger?->debug('Strategic question created successfully', [
            'question_id' => $strategicQuestion->getId(),
            'watch_file_id' => $action->watchFileId,
        ]);

        return $strategicQuestion;
    }

    private function getMessage(string $messageId): ?Message
    {
        try {
            return $this->messageGateway->get($messageId);
        } catch (MessageNotFoundException $e) {
            $this->logger?->error('Failed to retrieve message', [
                'message_id' => $messageId,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
