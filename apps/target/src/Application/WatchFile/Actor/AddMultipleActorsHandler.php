<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Actor;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Actor\ActorType;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class AddMultipleActorsHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(AddMultipleActorsAction $action): WatchFile
    {
        $watchFile = $this->getWatchFile($action->watchFileId);

        try {
            $this->logger?->info('Processing multiple actors detection', [
                'watch_file_id' => $action->watchFileId,
                'actors_count' => \count($action->actors),
            ]);

            foreach ($action->actors as $actorData) {
                $this->processActorData($action->watchFileId, $actorData);
            }

            $this->logger?->info('Multiple actors processed successfully', [
                'watch_file_id' => $watchFile->getId(),
                'actors_processed' => \count($action->actors),
            ]);

            return $watchFile;
        } catch (\Exception $e) {
            $this->logger?->error('Failed to process multiple actors', [
                'watch_file_id' => $action->watchFileId,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process individual actor data by dispatching to AddActorHandler.
     *
     * @param array<string, mixed> $actorData
     */
    private function processActorData(string $watchFileId, array $actorData): void
    {
        if (!isset($actorData['name']) || !\is_string($actorData['name'])) {
            $this->logger?->warning('Invalid actor data: missing or invalid name', [
                'actor_data' => $actorData,
            ]);

            return;
        }

        try {
            /** @var array{fr: string, en: string} $explanation */
            $explanation = $actorData['explanation'];

            $actorType = \is_string($actorData['type'] ?? null)
                ? ActorType::fromRoleString($actorData['type'])
                : ActorType::OTHER;

            $addActorAction = new AddActorAction(
                watchFileId: $watchFileId,
                name: $actorData['name'],
                type: $actorType,
                explanation: TranslatedText::fromArray($explanation),
                primaryDomain: \is_string($actorData['primaryDomain'] ?? null) ? $actorData['primaryDomain'] : null,
                score: is_numeric($actorData['score'] ?? null) ? (float) $actorData['score'] : null
            );

            $this->messageBus->dispatch($addActorAction);

            $this->logger?->debug('Actor action dispatched successfully', [
                'actor_name' => $actorData['name'],
                'actor_type' => $actorType->value,
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Failed to dispatch actor action', [
                'actor_name' => $actorData['name'],
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
