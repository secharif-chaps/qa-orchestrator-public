<?php

declare(strict_types=1);

namespace App\Application\Document;

use App\Application\WatchFile\Actor\AddActorAction;
use App\Application\WatchFile\Actor\AddActorHandler;
use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Actor\ActorGatewayInterface;
use App\Domain\Actor\ActorName;
use App\Domain\Actor\ActorType;
use App\Domain\Shared\TranslatedText;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
class ExtractActorsFromDocumentEventsHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private readonly ActorGatewayInterface $actorGateway,
        private readonly AddActorHandler $addActorHandler,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return array{successful: array<string, string|null>, failed: array<string, string>, total: int, successCount: int, failureCount: int}
     */
    public function __invoke(ExtractActorsFromDocumentEventsAction $action): array
    {
        $this->logger?->info('ExtractActorsFromDocumentEventsHandler: Starting actor extraction', [
            'documentId' => $action->documentId,
            'watchFileId' => $action->watchFileId,
            'eventCount' => \count($action->events),
        ]);

        $this->getWatchFile($action->watchFileId);

        $uniqueActors = $this->extractUniqueActors($action->events);

        $this->logger?->info('ExtractActorsFromDocumentEventsHandler: Unique actors extracted', [
            'uniqueActorCount' => \count($uniqueActors),
            'actorNames' => array_column($uniqueActors, 'display'),
            'normalizedNames' => array_keys($uniqueActors),
        ]);

        $displayNames = array_column($uniqueActors, 'display');
        $existingActors = $this->actorGateway->getByLabels($displayNames);

        $successfulActors = [];
        $failedActors = [];

        foreach ($uniqueActors as $normalizedName => $actorData) {
            $displayName = $actorData['display'];
            $actorType = $actorData['type'];

            try {
                $addActorAction = new AddActorAction(
                    watchFileId: $action->watchFileId,
                    name: $displayName,
                    type: $actorType,
                    explanation: new TranslatedText('Actor extracted from document', 'Acteur extrait du document'),
                    primaryDomain: null,
                    score: 0.5,
                    messageId: null
                );

                $actor = ($this->addActorHandler)($addActorAction);
                $successfulActors[$displayName] = $actor->getId();
            } catch (\Exception $e) {
                $failedActors[$displayName] = $e->getMessage();

                $this->logger?->error('ExtractActorsFromDocumentEventsHandler: Failed to process actor', [
                    'actorName' => $displayName,
                    'normalizedName' => $normalizedName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $result = [
            'successful' => $successfulActors,
            'failed' => $failedActors,
            'total' => \count($successfulActors) + \count($failedActors),
            'successCount' => \count($successfulActors),
            'failureCount' => \count($failedActors),
        ];

        $this->logger?->info('ExtractActorsFromDocumentEventsHandler: Extraction completed', [
            'successCount' => $result['successCount'],
            'failureCount' => $result['failureCount'],
            'successfulActors' => array_keys($successfulActors),
            'failedActors' => array_keys($failedActors),
        ]);

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     *
     * @return array<string, array{display: string, type: ActorType}>
     */
    private function extractUniqueActors(array $events): array
    {
        $uniqueActors = [];

        foreach ($events as $event) {
            if (!isset($event['actors']) || !\is_array($event['actors'])) {
                continue;
            }

            foreach ($event['actors'] as $actor) {
                try {
                    Assert::isArray($actor, 'Actor must be an array');
                    Assert::keyExists($actor, 'name', 'Actor must have a name');
                    Assert::string($actor['name'], 'Actor name must be a string');

                    $actorName = ActorName::fromString($actor['name']);
                    $normalizedName = $actorName->normalized;

                    if (!isset($uniqueActors[$normalizedName])) {
                        $actorRole = $actor['role'] ?? '';
                        $actorType = ActorType::fromRoleString(\is_string($actorRole) ? $actorRole : '');

                        $uniqueActors[$normalizedName] = [
                            'display' => $actorName->display,
                            'type' => $actorType,
                        ];
                    }
                } catch (\InvalidArgumentException $e) {
                    $this->logger?->debug('ExtractActorsFromDocumentEventsHandler: Skipping invalid actor', [
                        'actor' => $actor,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }
        }

        return $uniqueActors;
    }
}
