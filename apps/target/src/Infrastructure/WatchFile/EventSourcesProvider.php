<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Source\Source;
use App\Domain\User\User;
use App\Domain\WatchFile\EventSources;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<EventSources>
 */
readonly class EventSourcesProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private WatchFileGatewayInterface $watchFileGateway,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): EventSources
    {
        $watchFileId = $uriVariables['watchFileId'] ?? throw new \InvalidArgumentException('Missing watchFileId');
        $eventId = $uriVariables['eventId'] ?? throw new \InvalidArgumentException('Missing eventId');

        if (!\is_string($watchFileId) || !\is_string($eventId)) {
            throw new \InvalidArgumentException('watchFileId and eventId must be strings');
        }

        // Ensure user is authenticated
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('User must be authenticated');
        }

        // Find the watchfile (permissions checked by API Platform filters)
        $watchFile = $this->watchFileGateway->get($watchFileId);

        // Find the specific event
        $event = $this->entityManager->getRepository(WatchFileActivity::class)->findOneBy([
            'id' => $eventId,
            'watchFile' => $watchFile,
        ]);

        if (!$event) {
            throw new NotFoundHttpException(\sprintf('Event with ID "%s" not found for this watchfile', $eventId));
        }

        // Extract source IDs from the event
        $sourceIds = $this->extractSourceIdsFromEvent($event);

        // Get enriched source data
        $sources = $this->getEnrichedSources($sourceIds);

        // Build event data
        $eventData = $this->buildEventData($event);

        return new EventSources($eventData, $sources, \count($sources));
    }

    /**
     * @return list<string>
     */
    private function extractSourceIdsFromEvent(WatchFileActivity $event): array
    {
        $actionData = $event->getActionData();
        $actionType = $event->getActionType();

        $sourceId = $actionData['source_id'] ?? null;

        return match ($actionType) {
            WatchFileActivityActionType::SOURCE_STATUS_CHANGED => \is_string($sourceId) ? [$sourceId] : [],
            WatchFileActivityActionType::SOURCE_ADDED => \is_string($sourceId) ? [$sourceId] : [],
            default => [],
        };
    }

    /**
     * @param list<string> $sourceIds
     *
     * @return Source[]
     */
    private function getEnrichedSources(array $sourceIds): array
    {
        if (empty($sourceIds)) {
            return [];
        }

        // Get sources directly
        $qb = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Source::class, 's')
            ->where('s.id IN (:sourceIds)')
            ->setParameter('sourceIds', $sourceIds);

        /** @var Source[] $sources */
        $sources = $qb->getQuery()
->getResult();

        return $sources;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEventData(WatchFileActivity $event): array
    {
        $user = $event->getUser();

        return [
            'id' => $event->getId() ?? '',
            'type' => $event->getActionType()
->value,
            'timestamp' => $event->getCreatedAt()
->format('c'),
            'user' => [
                'id' => $user->getId() ?? '',
                'name' => $user->getDisplayName(),
                'email' => $user->getEmail() ?? '',
            ],
            'context' => $this->generateContext($event->getActionType(), $event->getActionData()),
            'actionData' => $event->getActionData(),
        ];
    }

    /**
     * @param array<string, mixed> $actionData
     */
    private function generateContext(WatchFileActivityActionType $actionType, array $actionData): string
    {
        return match ($actionType) {
            WatchFileActivityActionType::SOURCE_STATUS_CHANGED => $this->generateSourceStatusChangedContext(
                $actionData
            ),
            default => 'Timeline event',
        };
    }

    /**
     * @param array<string, mixed> $actionData
     */
    private function generateSourceStatusChangedContext(array $actionData): string
    {
        $sourceName = \is_string($actionData['source_name'] ?? null) ? $actionData['source_name'] : 'Unknown source';
        $oldStatus = \is_string($actionData['old_status'] ?? null) ? $actionData['old_status'] : '?';
        $newStatus = \is_string($actionData['new_status'] ?? null) ? $actionData['new_status'] : '?';

        return "Source {$sourceName} status changed: {$oldStatus} → {$newStatus}";
    }
}
