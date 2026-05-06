<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Source;

use App\Application\WatchFile\GetWatchFileTrait;
use App\Domain\Actor\ActorGatewayInterface;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\Source\DomainMatcher;
use App\Domain\Source\Event\SourceAddedToWatchFileEvent;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\UsageLimit\Exception\QuotaExceededException;
use App\Domain\UsageLimit\UsageLimitConfigInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileActorGatewayInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class AddSourceHandler
{
    use GetWatchFileTrait;

    public function __construct(
        private MessageGatewayInterface $messageGateway,
        private SourceGatewayInterface $sourceGateway,
        private ActorGatewayInterface $actorGateway,
        private WatchFileActorGatewayInterface $watchFileActorGateway,
        private UsageLimitConfigInterface $usageLimitConfig,
        private EventDispatcherInterface $eventDispatcher,
        private RealTimeUpdatePublisherInterface $realTimeUpdatePublisher,
        private DomainMatcher $domainMatcher,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(AddSourceAction $action): WatchFile
    {
        $this->logger?->debug('Add source action received');

        $watchFile = $this->getWatchFile($action->watchFileId);

        $user = $watchFile->getCreatedBy();
        $message = null;

        if (!empty($action->messageId)) {
            try {
                $message = $this->getMessage($action->messageId);
                $messageCreator = $message->getCreatedBy();
                if ($messageCreator instanceof User) {
                    $user = $messageCreator;
                }
            } catch (\Exception $e) {
                $this->logger?->warning('Failed to get message, using watchfile creator as fallback', [
                    'message_id' => $action->messageId,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        try {
            $source = $action->toEntity();
            if (null !== $action->actorId && null === $source->getActor()) {
                $actor = $this->actorGateway->getByWatchFileAndId($action->actorId, $watchFile->getId());

                if (null !== $actor) {
                    $source->setActor($actor);
                } else {
                    $this->logger?->warning(
                        \sprintf(
                            'Actor "%s" not found or not linked to watchfile "%s"',
                            $action->actorId,
                            $watchFile->getId()
                        )
                    );
                }
            }

            // Automatic actor linking by domain when no actor is explicitly provided
            if (null === $action->actorId && null === $source->getActor()) {
                $this->tryAutomaticActorLinking($source, $watchFile);
            }

            if (null !== $message) {
                $source->setAddedByMessage($message);
            }

            $alreadyExist = $this->sourceGateway->alreadyExist($watchFile, $source);
            if ($alreadyExist) {
                $this->logger?->info(
                    \sprintf(
                        'One source "%s" (%s) already exist fot the watchfile "%s"',
                        $source
                            ->getType()
                            ->value,
                        $source->getUrl(),
                        $watchFile->getId()
                    ),
                    [
                        'source' => $source,
                        'watchfile' => $watchFile,
                    ],
                );

                return $watchFile;
            }

            // Check quota before adding the source
            $resourceCount = $this->sourceGateway->countSourcesForWatchFile($watchFile);
            $quotaLimit = $this->usageLimitConfig->sourceMaxPerWatchFile();

            if ($resourceCount->exceeds($quotaLimit)) {
                $this->logger?->warning('Source quota exceeded for watchfile', [
                    'watch_file_id' => $watchFile->getId(),
                    'current_count' => $resourceCount->value(),
                    'limit' => $quotaLimit->value(),
                ]);

                throw QuotaExceededException::forSourceCreation($resourceCount->value(), $quotaLimit->value() ?? 0);
            }

            $watchFile->addSource($source);

            $this->watchFileGateway->save($watchFile);

            // Publish real-time update to all authorized users
            $this->realTimeUpdatePublisher->publishWatchFileUpdate($watchFile);

            if ($user instanceof User) {
                $this->eventDispatcher->dispatch(new SourceAddedToWatchFileEvent(
                    source: $source,
                    watchFile: $watchFile,
                    user: $user,
                ));
            }

            $this->logger?->info('Source successfully added to watchfile', [
                'watch_file_id' => $action->watchFileId,
                'source' => $source,
            ]);

            return $watchFile;
        } catch (\Exception $e) {
            $this->logger?->error('Failed to add source to watchfile', [
                'watch_file_id' => $action->watchFileId,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
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

    /**
     * Attempts to automatically link a source to an actor based on domain matching.
     *
     * This method:
     * 1. Extracts the domain from the source (using primaryDomain or extracting from URL)
     * 2. Iterates through actors associated with the WatchFile
     * 3. Compares normalized domains (case-insensitive, www prefix handled)
     * 4. If a match is found, sets the actor on the source
     *
     * Edge cases are handled gracefully:
     * - If domain extraction fails, logs a warning and continues without linking
     * - If no actors exist, skips matching
     * - If no match is found, source is created without actor link
     * - Never throws exceptions that would prevent source creation
     *
     * @param Source    $source    The source to link an actor to
     * @param WatchFile $watchFile The watchfile containing the actors to match against
     */
    private function tryAutomaticActorLinking(Source $source, WatchFile $watchFile): void
    {
        // Extract source domain - prefer primaryDomain, fallback to extracting from URL
        $sourceDomain = $source->getPrimaryDomain();
        if (empty($sourceDomain)) {
            $sourceDomain = $this->domainMatcher->extractDomainFromUrl($source->getUrl());
        }

        if (null === $sourceDomain || '' === trim($sourceDomain)) {
            $this->logger?->warning('Cannot extract domain for automatic actor linking', [
                'watch_file_id' => $watchFile->getId(),
                'source_url' => $source->getUrl(),
                'source_primary_domain' => $source->getPrimaryDomain(),
            ]);

            return;
        }

        // Normalize source domain for comparison
        $normalizedSourceDomain = $this->domainMatcher->normalizeDomain($sourceDomain);
        if (null === $normalizedSourceDomain) {
            $this->logger?->warning('Failed to normalize source domain for automatic actor linking', [
                'watch_file_id' => $watchFile->getId(),
                'source_domain' => $sourceDomain,
            ]);

            return;
        }

        // Query database directly for matching actor by normalized domain
        // This is much more efficient than loading all actors into memory, especially for large watchfiles
        $matchedActor = $this->watchFileActorGateway->findActorByNormalizedDomain(
            $watchFile->getId(),
            $normalizedSourceDomain
        );

        if (null !== $matchedActor) {
            $source->setActor($matchedActor);

            $this->logger?->info('Automatically linked source to actor by domain match', [
                'watch_file_id' => $watchFile->getId(),
                'source_domain' => $sourceDomain,
                'normalized_source_domain' => $normalizedSourceDomain,
                'actor_id' => $matchedActor->getId(),
                'actor_domain' => $matchedActor->getPrimaryDomain(),
            ]);
        }

        // No match found - this is expected behavior, no logging needed
    }
}
