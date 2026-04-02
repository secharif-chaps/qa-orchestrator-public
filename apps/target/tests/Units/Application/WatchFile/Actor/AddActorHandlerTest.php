<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\Actor;

use App\Application\WatchFile\Actor\AddActorAction;
use App\Application\WatchFile\Actor\AddActorHandler;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorGatewayInterface;
use App\Domain\Actor\ActorType;
use App\Domain\Chat\Message;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\UsageLimit\Exception\QuotaExceededException;
use App\Domain\UsageLimit\QuotaLimit;
use App\Domain\UsageLimit\UsageLimitConfigInterface;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Actor\NullActorGateway;
use App\Tests\Units\Infrastructure\Chat\NullMessageGateway;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class AddActorHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private NullWatchFileGateway $watchFileGateway;
    private NullActorGateway $actorGateway;
    private NullMessageGateway $messageGateway;
    private NullSourceGateway $sourceGateway;
    private AddActorHandler $handler;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->actorGateway = new NullActorGateway();
        $this->messageGateway = new NullMessageGateway();
        $this->sourceGateway = new NullSourceGateway();
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $security = $this->createStub(Security::class);
        $limitConfig = $this->createStub(UsageLimitConfigInterface::class);
        $realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);
        $this->handler = new AddActorHandler(
            $this->messageGateway,
            $this->actorGateway,
            $this->sourceGateway,
            $eventDispatcher,
            $security,
            $limitConfig,
            $realTimeUpdatePublisher,
        );
        $this->handler->setWatchFileGateway($this->watchFileGateway);
    }

    public function testAddNewActorToWatchFile(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'Test Actor',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Pertinent en français',
                'en' => 'Relevant in English',
            ]),
            primaryDomain: 'test.com',
            score: 0.75
        );

        $actor = ($this->handler)($action);

        $this->assertInstanceOf(Actor::class, $actor);
        $this->assertEquals('Test Actor', $actor->getLabel());
        $this->assertEquals('test.com', $actor->getPrimaryDomain());

        // Verify the actor was added to the watch file
        $updatedWatchFile = $this->watchFileGateway->get('watch_file_id');
        $watchFileActors = $updatedWatchFile->getWatchFileActors();

        $this->assertCount(1, $watchFileActors);
        $watchFileActor = $watchFileActors->first();
        $this->assertEquals('Test Actor', $watchFileActor->getActor()->getLabel());
        $this->assertEquals(ActorType::COMPETITOR, $watchFileActor->getType());
        $explanations = $watchFileActor->getExplanations();
        $this->assertInstanceOf(TranslatedText::class, $explanations);
        $this->assertEquals('Pertinent en français', $explanations->fr);
        $this->assertEquals('Relevant in English', $explanations->en);
        $this->assertEquals('test.com', $watchFileActor->getActor()->getPrimaryDomain());
        $this->assertEquals(0.75, $watchFileActor->getScore());
    }

    public function testAddExistingActorToWatchFile(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $existingActor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $existingActor->setPrimaryDomain('old-domain.com');
        $this->actorGateway->save($existingActor);

        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'Test Actor',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Pertinent en français',
                'en' => 'Relevant in English',
            ]),
            primaryDomain: 'new-domain.com',
            score: 0.75
        );

        $actor = ($this->handler)($action);

        $this->assertInstanceOf(Actor::class, $actor);
        $this->assertEquals('Test Actor', $actor->getLabel());
        $this->assertEquals('new-domain.com', $actor->getPrimaryDomain());

        // Verify the actor was added to the watch file
        $updatedWatchFile = $this->watchFileGateway->get('watch_file_id');
        $watchFileActors = $updatedWatchFile->getWatchFileActors();

        $this->assertCount(1, $watchFileActors);
        $watchFileActor = $watchFileActors->first();
        $this->assertEquals('Test Actor', $watchFileActor->getActor()->getLabel());
        $this->assertEquals('new-domain.com', $watchFileActor->getActor()->getPrimaryDomain());
    }

    public function testAddActorWithMessage(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $message = new Message();
        $this->forcePropertyValue($message, 'message_id');
        $this->messageGateway->save($message);

        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'Test Actor',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Pertinent en français',
                'en' => 'Relevant in English',
            ]),
            primaryDomain: 'test.com',
            score: 0.75,
            messageId: 'message_id'
        );

        $actor = ($this->handler)($action);

        $this->assertInstanceOf(Actor::class, $actor);
        $this->assertEquals('Test Actor', $actor->getLabel());

        // Verify the actor was added to the watch file with message
        $updatedWatchFile = $this->watchFileGateway->get('watch_file_id');
        $watchFileActors = $updatedWatchFile->getWatchFileActors();

        $this->assertCount(1, $watchFileActors);
        $watchFileActor = $watchFileActors->first();
        $addedByMessage = $watchFileActor->getAddedByMessage();
        $this->assertInstanceOf(Message::class, $addedByMessage);
        $this->assertEquals('message_id', $addedByMessage->getId());
    }

    public function testAddActorWithInvalidMessageContent(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'Test Actor',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Pertinent en français',
                'en' => 'Relevant in English',
            ]),
            primaryDomain: 'test.com',
            score: 0.75,
            messageId: 'invalid-message-content-id'
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        ($this->handler)($action);
    }

    public function testWatchFileNotFoundThrowsException(): void
    {
        $action = new AddActorAction(
            watchFileId: 'invalid-watchfile-id',
            name: 'Test Actor',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Pertinent en français',
                'en' => 'Relevant in English',
            ]),
            primaryDomain: 'test.com',
            score: 0.75
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        ($this->handler)($action);
    }

    public function testAddActorFailsWhenQuotaExceeded(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Mock actorGateway to return count of 75 (at limit)
        $actorGateway = $this->createStub(ActorGatewayInterface::class);
        $actorGateway->method('countByWatchFileId')
            ->willReturn(75);

        // Mock limitConfig to return limit of 75
        $limitConfig = $this->createStub(UsageLimitConfigInterface::class);
        $limitConfig->method('actorMaxPerWatchFile')
            ->willReturn(QuotaLimit::fromNullableInt(75));

        $sourceGateway = new NullSourceGateway();
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $security = $this->createStub(Security::class);
        $realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);

        $handler = new AddActorHandler(
            $this->messageGateway,
            $actorGateway,
            $sourceGateway,
            $eventDispatcher,
            $security,
            $limitConfig,
            $realTimeUpdatePublisher,
        );
        $handler->setWatchFileGateway($this->watchFileGateway);

        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'New Actor',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Nouvel acteur',
                'en' => 'New actor',
            ]),
            primaryDomain: 'newactor.com',
            score: 0.75
        );

        $this->expectException(QuotaExceededException::class);
        $this->expectExceptionMessage('quota.actor_max_per_watchfile');
        $handler($action);
    }

    public function testAddActorSucceedsWhenQuotaNotExceeded(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Mock actorGateway to return count of 50 (well below limit)
        $actorGateway = $this->createMock(ActorGatewayInterface::class);
        $actorGateway->method('countByWatchFileId')
            ->willReturn(50);
        $actorGateway->method('getByLabel')
            ->willThrowException(new \App\Domain\Actor\ActorNotFoundException('Actor not found'));
        $actorGateway->expects($this->once())
            ->method('save');

        // Mock limitConfig to return limit of 75
        $limitConfig = $this->createStub(UsageLimitConfigInterface::class);
        $limitConfig->method('actorMaxPerWatchFile')
            ->willReturn(QuotaLimit::fromNullableInt(75));

        $sourceGateway = new NullSourceGateway();
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $security = $this->createStub(Security::class);
        $realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);

        $handler = new AddActorHandler(
            $this->messageGateway,
            $actorGateway,
            $sourceGateway,
            $eventDispatcher,
            $security,
            $limitConfig,
            $realTimeUpdatePublisher,
        );
        $handler->setWatchFileGateway($this->watchFileGateway);

        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'New Actor',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Nouvel acteur',
                'en' => 'New actor',
            ]),
            primaryDomain: 'newactor.com',
            score: 0.75
        );

        $actor = $handler($action);
        $this->assertInstanceOf(Actor::class, $actor);
        $this->assertEquals('New Actor', $actor->getLabel());
    }

    public function testAddActorSucceedsWhenJustBelowLimit(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Mock actorGateway to return count of 74 (one below limit)
        $actorGateway = $this->createMock(ActorGatewayInterface::class);
        $actorGateway->method('countByWatchFileId')
            ->willReturn(74);
        $actorGateway->method('getByLabel')
            ->willThrowException(new \App\Domain\Actor\ActorNotFoundException('Actor not found'));
        $actorGateway->expects($this->once())
            ->method('save');

        // Mock limitConfig to return limit of 75
        $limitConfig = $this->createStub(UsageLimitConfigInterface::class);
        $limitConfig->method('actorMaxPerWatchFile')
            ->willReturn(QuotaLimit::fromNullableInt(75));

        $sourceGateway = new NullSourceGateway();
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $security = $this->createStub(Security::class);
        $realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);

        $handler = new AddActorHandler(
            $this->messageGateway,
            $actorGateway,
            $sourceGateway,
            $eventDispatcher,
            $security,
            $limitConfig,
            $realTimeUpdatePublisher,
        );
        $handler->setWatchFileGateway($this->watchFileGateway);

        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: '75th Actor',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => '75ème acteur',
                'en' => '75th actor',
            ]),
            primaryDomain: '75th.com',
            score: 0.75
        );

        $actor = $handler($action);
        $this->assertInstanceOf(Actor::class, $actor);
        $this->assertEquals('75th Actor', $actor->getLabel());
    }

    public function testDeduplicateActorByPrimaryDomain(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Create an existing actor with a specific primary domain
        $existingActor = new Actor('Existing Actor Name', new Organisation('Test Org', 'test-org-id'));
        $existingActor->setPrimaryDomain('example.com');
        $this->actorGateway->save($existingActor);

        // Try to add an actor with a DIFFERENT label but the SAME primary domain
        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'Different Actor Name',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Explication',
                'en' => 'Explanation',
            ]),
            primaryDomain: 'https://example.com/some/path',
            score: 0.8
        );

        $actor = ($this->handler)($action);

        // Should return the existing actor (deduplicated by primaryDomain)
        $this->assertInstanceOf(Actor::class, $actor);
        $this->assertEquals('Existing Actor Name', $actor->getLabel());
        $this->assertEquals('example.com', $actor->getPrimaryDomain());

        // Verify the existing actor was linked to the watch file
        $updatedWatchFile = $this->watchFileGateway->get('watch_file_id');
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);
    }

    public function testDeduplicateActorByPrimaryDomainWithFullUrl(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Create an existing actor with a specific primary domain
        $existingActor = new Actor('Company A', new Organisation('Test Org', 'test-org-id'));
        $existingActor->setPrimaryDomain('company-a.com');
        $this->actorGateway->save($existingActor);

        // Try to add with full URL containing the same domain
        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'Company A - Division B',
            type: ActorType::PARTNER,
            explanation: TranslatedText::fromArray([
                'fr' => 'Division B de Company A',
                'en' => 'Division B of Company A',
            ]),
            primaryDomain: 'https://company-a.com/division-b/',
            score: 0.9
        );

        $actor = ($this->handler)($action);

        // Should return the existing actor
        $this->assertEquals('Company A', $actor->getLabel());
        $this->assertEquals('company-a.com', $actor->getPrimaryDomain());
    }

    public function testNoDeduplicationWhenDifferentDomain(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Create an existing actor with a specific primary domain
        $existingActor = new Actor('Existing Actor', new Organisation('Test Org', 'test-org-id'));
        $existingActor->setPrimaryDomain('existing.com');
        $this->actorGateway->save($existingActor);

        // Try to add an actor with different label AND different domain
        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'New Actor',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Nouvel acteur',
                'en' => 'New actor',
            ]),
            primaryDomain: 'newactor.com',
            score: 0.7
        );

        $actor = ($this->handler)($action);

        // Should create a new actor since both label and domain are different
        $this->assertInstanceOf(Actor::class, $actor);
        $this->assertEquals('New Actor', $actor->getLabel());
        $this->assertEquals('newactor.com', $actor->getPrimaryDomain());
    }

    public function testLabelMatchTakesPrecedenceOverDomainMatch(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Create an actor with label "Target Actor" and domain "other.com"
        $actorByLabel = new Actor('Target Actor', new Organisation('Test Org', 'test-org-id'));
        $actorByLabel->setPrimaryDomain('other.com');
        $this->forcePropertyValue($actorByLabel, 'actor-by-label-id');
        $this->actorGateway->save($actorByLabel);

        // Create another actor with different label but domain "target.com"
        $actorByDomain = new Actor('Different Name', new Organisation('Test Org', 'test-org-id'));
        $actorByDomain->setPrimaryDomain('target.com');
        $this->forcePropertyValue($actorByDomain, 'actor-by-domain-id');
        $this->actorGateway->save($actorByDomain);

        // Add actor with matching label and domain that matches the second actor
        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'Target Actor',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Test',
                'en' => 'Test',
            ]),
            primaryDomain: 'target.com',
            score: 0.5
        );

        $actor = ($this->handler)($action);

        // Label match should take precedence - should return actor with matching label
        $this->assertEquals('Target Actor', $actor->getLabel());
        // Domain should be updated to the new one
        $this->assertEquals('target.com', $actor->getPrimaryDomain());
    }

    public function testDeduplicationWithNullPrimaryDomain(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Create an existing actor with a primary domain
        $existingActor = new Actor('Some Actor', new Organisation('Test Org', 'test-org-id'));
        $existingActor->setPrimaryDomain('some.com');
        $this->actorGateway->save($existingActor);

        // Add actor without primary domain - should create new actor
        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'New Actor Without Domain',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Sans domaine',
                'en' => 'Without domain',
            ]),
            primaryDomain: null,
            score: 0.6
        );

        $actor = ($this->handler)($action);

        // Should create a new actor since no domain deduplication can occur
        $this->assertEquals('New Actor Without Domain', $actor->getLabel());
        $this->assertNull($actor->getPrimaryDomain());
    }

    public function testDeduplicationDoesNotOccurWithInvalidDomain(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Create an existing actor
        $existingActor = new Actor('Existing', new Organisation('Test Org', 'test-org-id'));
        $existingActor->setPrimaryDomain('existing.com');
        $this->actorGateway->save($existingActor);

        // Add actor with invalid domain (no TLD)
        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'New Actor',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Test',
                'en' => 'Test',
            ]),
            primaryDomain: 'invalid-domain-without-tld',
            score: 0.5
        );

        $actor = ($this->handler)($action);

        // Should create a new actor since the domain is invalid
        $this->assertEquals('New Actor', $actor->getLabel());
        // Primary domain should remain null since it was invalid
        $this->assertNull($actor->getPrimaryDomain());
    }

    public function testLinkOrphanedSourcesByDomain(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Create orphaned sources (without actor) with matching domain
        $orphanedSource1 = new Source(
            'Source 1',
            new TranslatedText('Description FR 1', 'Description EN 1'),
            SourceType::WEBSITE,
            'https://acme.com/page1',
            'acme.com',
            new TranslatedText('Relevance FR 1', 'Relevance EN 1'),
            null, // No actor (orphaned)
            $watchFile
        );
        $this->sourceGateway->save($orphanedSource1);

        $orphanedSource2 = new Source(
            'Source 2',
            new TranslatedText('Description FR 2', 'Description EN 2'),
            SourceType::WEBSITE,
            'https://www.acme.com/page2',
            'www.acme.com',
            new TranslatedText('Relevance FR 2', 'Relevance EN 2'),
            null, // No actor (orphaned)
            $watchFile
        );
        $this->sourceGateway->save($orphanedSource2);

        // Create a source with different domain (should not be linked)
        $otherSource = new Source(
            'Other Source',
            new TranslatedText('Description FR', 'Description EN'),
            SourceType::WEBSITE,
            'https://other.com/page',
            'other.com',
            new TranslatedText('Relevance FR', 'Relevance EN'),
            null, // No actor (orphaned)
            $watchFile
        );
        $this->sourceGateway->save($otherSource);

        // Create actor with domain matching the orphaned sources
        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'ACME Corporation',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Concurrent ACME',
                'en' => 'ACME Competitor',
            ]),
            primaryDomain: 'acme.com',
            score: 0.8
        );

        $actor = ($this->handler)($action);

        // Verify actor was created
        $this->assertEquals('ACME Corporation', $actor->getLabel());
        $this->assertEquals('acme.com', $actor->getPrimaryDomain());

        // Verify orphaned sources were linked to the actor
        $this->assertSame($actor, $orphanedSource1->getActor(), 'First orphaned source should be linked to actor');
        $this->assertSame($actor, $orphanedSource2->getActor(), 'Second orphaned source should be linked to actor');
        $this->assertNull($otherSource->getActor(), 'Source with different domain should not be linked');
    }

    public function testLinkOrphanedSourcesWithWwwVariation(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Create orphaned source with www prefix
        $orphanedSource = new Source(
            'Source with WWW',
            new TranslatedText('Description FR', 'Description EN'),
            SourceType::WEBSITE,
            'https://www.example.com/page',
            'www.example.com',
            new TranslatedText('Relevance FR', 'Relevance EN'),
            null, // No actor (orphaned)
            $watchFile
        );
        $this->sourceGateway->save($orphanedSource);

        // Create actor with domain without www
        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'Example Company',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Exemple',
                'en' => 'Example',
            ]),
            primaryDomain: 'example.com', // Without www
            score: 0.7
        );

        $actor = ($this->handler)($action);

        // Verify actor was created
        $this->assertEquals('Example Company', $actor->getLabel());
        $this->assertEquals('example.com', $actor->getPrimaryDomain());

        // Verify orphaned source was linked despite www variation
        $this->assertSame($actor, $orphanedSource->getActor(), 'Source with www should be linked to actor without www');
    }

    public function testNoLinkWhenActorHasNoDomain(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Create orphaned source
        $orphanedSource = new Source(
            'Orphaned Source',
            new TranslatedText('Description FR', 'Description EN'),
            SourceType::WEBSITE,
            'https://example.com/page',
            'example.com',
            new TranslatedText('Relevance FR', 'Relevance EN'),
            null, // No actor (orphaned)
            $watchFile
        );
        $this->sourceGateway->save($orphanedSource);

        // Create actor without domain
        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'Actor Without Domain',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Sans domaine',
                'en' => 'Without domain',
            ]),
            primaryDomain: null, // No domain
            score: 0.6
        );

        $actor = ($this->handler)($action);

        // Verify actor was created without domain
        $this->assertEquals('Actor Without Domain', $actor->getLabel());
        $this->assertNull($actor->getPrimaryDomain());

        // Verify orphaned source was NOT linked (no domain matching possible)
        $this->assertNull($orphanedSource->getActor(), 'No sources should be linked when actor has no domain');
    }

    public function testNoLinkWhenNoOrphanedSources(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Create source with existing actor (not orphaned)
        $existingActor = new Actor('Existing Actor', new Organisation('Test Org', 'test-org-id'));
        $existingActor->setPrimaryDomain('example.com');
        $this->actorGateway->save($existingActor);

        $sourceWithActor = new Source(
            'Source With Actor',
            new TranslatedText('Description FR', 'Description EN'),
            SourceType::WEBSITE,
            'https://example.com/page',
            'example.com',
            new TranslatedText('Relevance FR', 'Relevance EN'),
            $existingActor, // Already has actor
            $watchFile
        );
        $this->sourceGateway->save($sourceWithActor);

        // Create new actor with different domain (to avoid deduplication)
        // This ensures we test that sources already linked are not relinked
        $action = new AddActorAction(
            watchFileId: 'watch_file_id',
            name: 'New Actor',
            type: ActorType::COMPETITOR,
            explanation: TranslatedText::fromArray([
                'fr' => 'Nouvel acteur',
                'en' => 'New actor',
            ]),
            primaryDomain: 'newactor.com',
            score: 0.7
        );

        $actor = ($this->handler)($action);

        // Verify new actor was created (not deduplicated)
        $this->assertEquals('New Actor', $actor->getLabel());
        $this->assertEquals('newactor.com', $actor->getPrimaryDomain());

        // Verify source with existing actor was NOT linked to new actor
        $this->assertSame(
            $existingActor,
            $sourceWithActor->getActor(),
            'Source should remain linked to existing actor'
        );
        $this->assertNotSame($actor, $sourceWithActor->getActor(), 'Source should not be relinked to new actor');
    }
}
