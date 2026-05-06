<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\EventActors;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileActor;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @implements ProviderInterface<EventActors>
 */
readonly class EventActorsProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private WatchFileGatewayInterface $watchFileGateway,
        private TranslatorInterface $translator,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): EventActors
    {
        $watchFileId = $uriVariables['watchFileId'] ?? null;
        $eventId = $uriVariables['eventId'] ?? null;

        if (!\is_string($watchFileId) || !\is_string($eventId)) {
            throw new BadRequestException('WatchFile ID and Event ID are required and must be strings');
        }

        // Get the current user
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('User must be authenticated');
        }

        // Find the watchfile and verify access
        try {
            $watchFile = $this->watchFileGateway->getForUser($watchFileId, $user);
            // Refresh the watchfile entity to ensure we have the latest data from database
            $this->entityManager->refresh($watchFile);
        } catch (WatchFileNotFoundException $e) {
            throw new NotFoundHttpException(\sprintf(
                'WatchFile with ID "%s" not found or access denied',
                $watchFileId
            ), $e);
        }

        // Verify user has at least read access to the watchfile
        if (!$this->security->isGranted(WatchFileVoter::VIEW, $watchFile)) {
            throw new AccessDeniedException('You do not have permission to access this watchfile');
        }

        // Find the specific event
        $event = $this->entityManager->getRepository(WatchFileActivity::class)->findOneBy([
            'id' => $eventId,
            'watchFile' => $watchFile,
        ]);

        if (!$event) {
            throw new NotFoundHttpException(\sprintf('Event with ID "%s" not found for this watchfile', $eventId));
        }

        // Extract actor IDs from event metadata
        $actorIds = $this->extractActorIdsFromEvent($event);

        // Get enriched actor data
        $actors = $this->getEnrichedActorData($actorIds, $watchFile);

        // Build event data
        $eventData = $this->buildEventData($event);

        return new EventActors($eventData, $actors);
    }

    /**
     * Extract actor IDs from the event's action data based on the event type.
     *
     * @return array<string>
     */
    private function extractActorIdsFromEvent(WatchFileActivity $event): array
    {
        $actionData = $event->getActionData();
        $actionType = $event->getActionType();

        $actorIds = match ($actionType) {
            WatchFileActivityActionType::ACTOR_ADDED => [$actionData['actor_id'] ?? null],
            WatchFileActivityActionType::ACTOR_STATUS_CHANGED => [$actionData['actor_id'] ?? null],
            default => [],
        };

        // Filter out null values and ensure all elements are strings
        return array_filter($actorIds, fn ($id) => \is_string($id));
    }

    /**
     * Get WatchFileActor entities for the given actor IDs.
     *
     * @param array<string> $actorIds
     *
     * @return WatchFileActor[]
     */
    private function getEnrichedActorData(array $actorIds, WatchFile $watchFile): array
    {
        $actorIds = array_filter($actorIds);

        if (empty($actorIds)) {
            return [];
        }

        // Get WatchFileActor entities directly
        $qb = $this->entityManager->createQueryBuilder()
            ->select('wfa')
            ->from(WatchFileActor::class, 'wfa')
            ->join('wfa.actor', 'a')
            ->where('a.id IN (:actorIds)')
            ->andWhere('wfa.watchFile = :watchFile')
            ->setParameter('actorIds', $actorIds)
            ->setParameter('watchFile', $watchFile);

        /** @var WatchFileActor[] $watchFileActors */
        $watchFileActors = $qb->getQuery()
->getResult();

        return $watchFileActors;
    }

    /**
     * Build the event data structure.
     *
     * @return array<string, mixed>
     */
    private function buildEventData(WatchFileActivity $event): array
    {
        $user = $event->getUser();

        return [
            'id' => $event->getId(),
            'type' => $this->mapActionTypeToTimelineType($event->getActionType()),
            'timestamp' => $event->getCreatedAt()
->format('c'),
            'user' => [
                'id' => $user->getId(),
                'name' => $this->getUserDisplayName($user),
                'email' => $user->getEmail(),
            ],
            'context' => $this->generateContext($event->getActionType(), $event->getActionData()),
            'metadata' => $event->getActionData(),
        ];
    }

    private function mapActionTypeToTimelineType(WatchFileActivityActionType $actionType): string
    {
        return match ($actionType) {
            WatchFileActivityActionType::ACTOR_ADDED => 'WATCHFILE_ACTOR_ADDED',
            WatchFileActivityActionType::ACTOR_STATUS_CHANGED => 'WATCHFILE_ACTOR_STATUS_CHANGED',
            default => 'WATCHFILE_' . strtoupper($actionType->value),
        };
    }

    private function getUserDisplayName(User $user): string
    {
        // Try to get a display name from user properties
        $firstName = $user->getFirstName();
        $lastName = $user->getLastName();
        if ($firstName && $lastName) {
            return trim($firstName . ' ' . $lastName);
        }

        // Fallback to email or username
        if ($user->getUsername()) {
            return $user->getUsername();
        }

        return $user->getEmail() ?? 'unknown@example.com';
    }

    /**
     * @param array<string, mixed> $actionData
     */
    private function generateContext(WatchFileActivityActionType $actionType, array $actionData): string
    {
        return match ($actionType) {
            WatchFileActivityActionType::ACTOR_ADDED => $this->generateActorAddedContext($actionData),
            WatchFileActivityActionType::ACTOR_STATUS_CHANGED => $this->generateActorStatusChangedContext($actionData),
            default => $this->translator->trans('timeline.event_default', [], 'messages'),
        };
    }

    /**
     * @param array<string, mixed> $actionData
     */
    private function generateActorAddedContext(array $actionData): string
    {
        $actorName = \is_string(
            $actionData['actor_name'] ?? null
        ) ? $actionData['actor_name'] : $this->translator->trans('actor.unknown', [], 'messages');
        $actorType = \is_string($actionData['actor_type'] ?? null) ? $actionData['actor_type'] : '';
        $primaryDomain = \is_string($actionData['primary_domain'] ?? null) ? $actionData['primary_domain'] : null;

        $context = $this->translator->trans('timeline.actor_added', [
            'actorName' => $actorName,
        ], 'messages');
        if (!empty($actorType)) {
            $context .= ' ' . $this->translator->trans('timeline.actor_type_suffix', [
                'type' => $actorType,
            ], 'messages');
        }
        if ($primaryDomain) {
            $context .= ' ' . $this->translator->trans('timeline.actor_domain_suffix', [
                'domain' => $primaryDomain,
            ], 'messages');
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $actionData
     */
    private function generateActorStatusChangedContext(array $actionData): string
    {
        $actorName = \is_string(
            $actionData['actor_name'] ?? null
        ) ? $actionData['actor_name'] : $this->translator->trans('actor.unknown', [], 'messages');
        $oldStatus = \is_string($actionData['old_status'] ?? null) ? $actionData['old_status'] : '';
        $newStatus = \is_string($actionData['status'] ?? null) ? $actionData['status'] : '';

        return $this->translator->trans('timeline.actor_status_changed', [
            'actorName' => $actorName,
            'oldStatus' => $oldStatus,
            'newStatus' => $newStatus,
        ], 'messages');
    }
}
