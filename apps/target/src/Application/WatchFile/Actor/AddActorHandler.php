<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Actor;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorGatewayInterface;
use App\Domain\Actor\ActorNotFoundException;
use App\Domain\Actor\ActorType;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\UsageLimit\Exception\QuotaExceededException;
use App\Domain\UsageLimit\ResourceCount;
use App\Domain\UsageLimit\UsageLimitConfigInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\Event\ActorAddedEvent;
use App\Domain\WatchFile\WatchFile;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
readonly class AddActorHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private MessageGatewayInterface $messageGateway,
        private ActorGatewayInterface $actorGateway,
        private SourceGatewayInterface $sourceGateway,
        private EventDispatcherInterface $eventDispatcher,
        private Security $security,
        private UsageLimitConfigInterface $limitConfig,
        private RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(AddActorAction $action): Actor
    {
        $watchFile = $this->getWatchFile($action->watchFileId);

        $this->checkActorQuota($action->watchFileId);

        $actor = $this->getOrCreateActor($action->name, $action->primaryDomain, $watchFile);
        if (null !== $action->primaryDomain) {
            $this->updateActorPrimaryDomain($actor, $action->primaryDomain);
        }

        $this->linkOrphanedSourcesByDomain($watchFile, $actor);

        $actorWasAdded = $this->addActorToWatchFileIfNotExists(
            $watchFile,
            $actor,
            $action->type,
            $action->explanation,
            $action->score ?? 0.0,
            $action->messageId ? $this->getMessage($action->messageId) : null,
        );

        $this->watchFileGateway->save($watchFile);

        // Publish real-time update to all authorized users
        $this->realTimeUpdatePublisher->publishWatchFileUpdate($watchFile);

        if ($actorWasAdded) {
            $this->logger?->info('Actor added to watchfile', [
                'watch_file_id' => $watchFile->getId(),
                'actor_name' => $actor->getLabel(),
                'actor_type' => $action->type->value,
            ]);

            // Get the user who added the actor (current user or watchfile creator as fallback)
            $addedBy = $this->security->getUser();
            if (!$addedBy instanceof User) {
                $addedBy = $watchFile->getCreatedBy();
            }

            // Dispatch the ActorAddedEvent
            if ($addedBy instanceof User) {
                $this->eventDispatcher->dispatch(new ActorAddedEvent(
                    watchFile: $watchFile,
                    actor: $actor,
                    addedBy: $addedBy,
                    actorType: $action->type,
                    explanation: $action->explanation,
                    score: $action->score
                ));
            }
        }

        return $actor;
    }

    /**
     * Get an existing actor by label or primary domain, or create a new one.
     *
     * Deduplication strategy:
     * 1. First, try to find by label (existing behavior)
     * 2. If not found and primaryDomain is provided, try to find by primaryDomain
     * 3. If still not found, create a new actor
     */
    private function getOrCreateActor(string $actorLabel, ?string $primaryDomain, WatchFile $watchFile): Actor
    {
        // First, try to find by label
        try {
            return $this->actorGateway->getByLabel($actorLabel);
        } catch (ActorNotFoundException) {
            // Actor not found by label, continue to check by primaryDomain
        }

        // If primaryDomain is provided, check if an actor with this domain already exists
        if (null !== $primaryDomain) {
            $extractedDomain = $this->extractDomainFromUrl($primaryDomain);
            if (null !== $extractedDomain) {
                $existingActor = $this->actorGateway->findByPrimaryDomain($extractedDomain);
                if (null !== $existingActor) {
                    $this->logger?->info('Found existing actor by primary domain', [
                        'label' => $actorLabel,
                        'existing_label' => $existingActor->getLabel(),
                        'primary_domain' => $extractedDomain,
                    ]);

                    return $existingActor;
                }
            }
        }

        // No existing actor found, create a new one
        $this->logger?->info('Creating new actor', [
            'label' => $actorLabel,
        ]);
        $actor = new Actor($actorLabel, $watchFile->getOrganisation());
        $this->actorGateway->save($actor);

        return $actor;
    }

    /**
     * Extract and update the primary domain for an actor if needed.
     */
    private function updateActorPrimaryDomain(Actor $actor, string $primaryDomain): void
    {
        $extractedDomain = $this->extractDomainFromUrl($primaryDomain);

        if (null === $extractedDomain) {
            $this->logger?->warning('Could not extract valid domain from URL', [
                'actor' => $actor->getLabel(),
                'input' => $primaryDomain,
            ]);

            return;
        }

        if ($extractedDomain !== $actor->getPrimaryDomain()) {
            $this->logger?->debug('Updating actor primary domain', [
                'actor' => $actor->getLabel(),
                'old_domain' => $actor->getPrimaryDomain(),
                'new_domain' => $extractedDomain,
            ]);

            $actor->setPrimaryDomain($extractedDomain);
        }
    }

    /**
     * Extract the domain name from a URL.
     *
     * @return string|null The extracted domain, or null if the URL is invalid
     */
    private function extractDomainFromUrl(string $url): ?string
    {
        $url = trim($url);

        if ('' === $url) {
            return null;
        }

        // Use parse_url for robust URL parsing
        $parsed = parse_url($url);

        // If host is present (full URL like "https://example.com/path"), use it
        if (\is_array($parsed) && isset($parsed['host'])) {
            $domain = $parsed['host'];
        } elseif (str_contains($url, '/')) {
            // For URLs without protocol (e.g., "example.com/path"), extract domain part
            $parts = explode('/', $url, 2);
            $domain = $parts[0];
        } else {
            // Plain domain without path
            $domain = $url;
        }

        // Validate the extracted domain looks like a valid domain
        // Must contain at least one dot and only valid domain characters
        if (!$this->isValidDomain($domain)) {
            return null;
        }

        return $domain;
    }

    /**
     * Check if a string looks like a valid domain name.
     */
    private function isValidDomain(string $domain): bool
    {
        // Must not be empty
        if ('' === $domain) {
            return false;
        }

        // Must contain at least one dot (e.g., "example.com")
        if (!str_contains($domain, '.')) {
            return false;
        }

        // Basic domain pattern: alphanumeric, hyphens, and dots
        // Allows international domains (IDN) by checking for valid structure
        return (bool) preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i', $domain);
    }

    /**
     * Add an actor to a watchfile if no relationship already exists.
     *
     * Business rule: an actor can only be linked once to a watchfile, regardless of type.
     * Uses a direct database query to check existence instead of loading
     * all actors into memory, which is more efficient for large watchfiles.
     */
    private function addActorToWatchFileIfNotExists(
        WatchFile $watchFile,
        Actor $actor,
        ActorType $actorType,
        TranslatedText $explanation,
        float $score,
        ?Message $message,
    ): bool {
        $watchFileId = $watchFile->getId();
        $actorId = $actor->getId();

        // Use direct query instead of loading all actors into memory
        // Note: actorId might be null for newly created actors in some test scenarios
        if (
            null !== $actorId
            && $this->watchFileGateway->hasActorRelation($watchFileId, $actorId)
        ) {
            $this->logger?->debug('Actor already linked to watchfile, skipping', [
                'watchfile' => $watchFileId,
                'actor' => $actor->getLabel(),
            ]);

            return false;
        }

        $this->logger?->debug('Adding actor to watchfile', [
            'watchfile' => $watchFileId,
            'actor' => $actor->getLabel(),
            'type' => $actorType->value,
        ]);

        $watchFile->addActor($actor, $actorType, $explanation, $score, $message);

        return true;
    }

    private function getMessage(string $messageId): Message
    {
        try {
            return $this->messageGateway->get($messageId);
        } catch (\Exception $e) {
            $this->logger?->error('Failed to retrieve message', [
                'message_id' => $messageId,
                'exception' => $e->getMessage(),
            ]);
            throw new UnrecoverableMessageHandlingException('Message not found', 0, $e);
        }
    }

    private function checkActorQuota(string $watchFileId): void
    {
        $currentCount = $this->actorGateway->countByWatchFileId($watchFileId);
        $limit = $this->limitConfig->actorMaxPerWatchFile();

        $resourceCount = ResourceCount::fromInt($currentCount);

        if ($limit->requiresLimitEnforcement($resourceCount)) {
            $limitValue = $limit->value();
            Assert::notNull($limitValue, 'Limit cannot be null when enforcement is required');
            $this->logger?->warning('Actor quota exceeded for watchfile', [
                'watch_file_id' => $watchFileId,
                'current_count' => $currentCount,
                'limit' => $limitValue,
            ]);

            throw QuotaExceededException::forActorCreation($currentCount, $limitValue);
        }
    }

    /**
     * Link orphaned sources (sources without actor) to the actor if they match the actor's domain.
     * This handles the retroactive linking of sources that were created before the actor.
     */
    private function linkOrphanedSourcesByDomain(WatchFile $watchFile, Actor $actor): void
    {
        $primaryDomain = $actor->getPrimaryDomain();

        // Only proceed if the actor has a primary domain
        if (null === $primaryDomain) {
            return;
        }

        // Normalize domain (remove www prefix if present)
        $normalizedDomain = $this->normalizeDomain($primaryDomain);

        // Find orphaned sources matching the domain
        $orphanedSources = $this->sourceGateway->findOrphanedSourcesByDomain($watchFile, $normalizedDomain);

        if (empty($orphanedSources)) {
            $this->logger?->debug('No orphaned sources found matching actor domain', [
                'watch_file_id' => $watchFile->getId(),
                'actor_id' => $actor->getId(),
                'domain' => $normalizedDomain,
            ]);

            return;
        }

        $this->logger?->info('Linking orphaned sources to actor', [
            'watch_file_id' => $watchFile->getId(),
            'actor_id' => $actor->getId(),
            'actor_label' => $actor->getLabel(),
            'domain' => $normalizedDomain,
            'sources_count' => \count($orphanedSources),
        ]);

        // Link each orphaned source to the actor
        foreach ($orphanedSources as $source) {
            $source->setActor($actor);
            $this->sourceGateway->save($source);
        }
    }

    /**
     * Normalize a domain by removing www prefix if present.
     * This ensures consistent domain comparison.
     */
    private function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        $lowerDomain = strtolower($domain);

        // Remove www. prefix if present
        if (str_starts_with($lowerDomain, 'www.')) {
            return substr($domain, 4);
        }

        return $domain;
    }
}
