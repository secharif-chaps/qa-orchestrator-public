<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\Source;

use App\Application\WatchFile\Source\AddSourceAction;
use App\Application\WatchFile\Source\AddSourceHandler;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorType;
use App\Domain\Chat\Message;
use App\Domain\Chat\MessageGatewayInterface;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\DomainMatcher;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceType;
use App\Domain\UsageLimit\Exception\QuotaExceededException;
use App\Domain\UsageLimit\QuotaLimit;
use App\Domain\UsageLimit\UsageLimitConfigInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileActorGatewayInterface;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Tests\Units\Infrastructure\Actor\NullActorGateway;
use App\Tests\Units\Infrastructure\Chat\NullMessageGateway;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileActorGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class AddSourceHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private WatchFileGatewayInterface $watchFileGateway;
    private MessageGatewayInterface $messageGateway;
    private SourceGatewayInterface $sourceGateway;
    private NullActorGateway $actorGateway;
    private WatchFileActorGatewayInterface $watchFileActorGateway;
    private UsageLimitConfigInterface&Stub $usageLimitConfig;
    private LoggerInterface&Stub $logger;
    private AddSourceHandler $handler;
    private EventDispatcherInterface&Stub $eventDispatcher;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->messageGateway = new NullMessageGateway();
        $this->sourceGateway = new NullSourceGateway();
        $this->actorGateway = new NullActorGateway();
        $this->actorGateway->setWatchFileGateway($this->watchFileGateway);
        $this->watchFileActorGateway = new NullWatchFileActorGateway();
        $this->watchFileActorGateway->setWatchFileGateway($this->watchFileGateway);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);

        // Mock UsageLimitConfig to return unlimited quota by default
        $this->usageLimitConfig = $this->createStub(UsageLimitConfigInterface::class);
        $this->usageLimitConfig
            ->method('sourceMaxPerWatchFile')
            ->willReturn(QuotaLimit::fromNullableInt(100, inclusive: false)); // Unlimited by default

        $realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);
        $domainMatcher = new DomainMatcher();

        $this->handler = new AddSourceHandler(
            $this->messageGateway,
            $this->sourceGateway,
            $this->actorGateway,
            $this->watchFileActorGateway,
            $this->usageLimitConfig,
            $this->eventDispatcher,
            $realTimeUpdatePublisher,
            $domainMatcher,
            $this->logger
        );
        $this->handler->setWatchFileGateway($this->watchFileGateway);
    }

    public function testAddSourceToWatchFile(): void
    {
        $user = new User(id: 'test-user-id', email: 'test@example.com', roles: ['ROLE_USER'], userName: 'testuser');
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, $user, 'createdBy');
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor_id');
        $this->actorGateway->save($actor);

        // Link the actor to the watchfile so the source can get the actor set
        $watchFile->addActor($actor, ActorType::SUPPLIER, null, 0.5, null);

        $action = new AddSourceAction(
            watchFileId: 'watch_file_id',
            name: 'Test Source',
            type: 'website',
            primaryDomain: 'test.com',
            url: 'https://test.com',
            query: 'test query',
            description: new TranslatedText('Description en français', 'Description in English'),
            relevance: new TranslatedText('Pertinent en français', 'Relevant in English'),
            parameters: [
                'param1' => 'value1',
            ],
            actorId: 'actor_id'
        );

        $updatedWatchFile = ($this->handler)($action);
        $sources = $updatedWatchFile->getSources();

        $this->assertCount(1, $sources);
        $source = $sources->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertEquals('Test Source', $source->getName());
        $this->assertEquals(SourceType::WEBSITE, $source->getType());
        $this->assertEquals('test.com', $source->getPrimaryDomain());
        $this->assertEquals('https://test.com', $source->getUrl());
        $this->assertEquals('test query', $source->getQuery());
        $description = $source->getDescription();
        $this->assertInstanceOf(TranslatedText::class, $description);
        $this->assertEquals('Description en français', $description->fr);
        $this->assertEquals('Description in English', $description->en);
        $relevance = $source->getRelevance();
        $this->assertInstanceOf(TranslatedText::class, $relevance);
        $this->assertEquals('Pertinent en français', $relevance->fr);
        $this->assertEquals('Relevant in English', $relevance->en);
        $this->assertEquals([
            'param1' => 'value1',
        ], $source->getParameters());
        $this->assertSame($actor, $source->getActor());
    }

    public function testAddSourceWithMessage(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor_id');
        $this->actorGateway->save($actor);

        // Link the actor to the watchfile so the source can get the actor set
        $watchFile->addActor($actor, ActorType::SUPPLIER, null, 0.5, null);

        $message = new Message();
        $this->forcePropertyValue($message, 'message_id');
        $this->assertNotNull($message->getId());
        $this->messageGateway->save($message);

        $action = new AddSourceAction(
            watchFileId: 'watch_file_id',
            name: 'Test Source',
            type: 'website',
            primaryDomain: 'test.com',
            url: 'https://test.com',
            query: 'test query',
            description: new TranslatedText('Description en français', 'Description in English'),
            relevance: new TranslatedText('Pertinent en français', 'Relevant in English'),
            parameters: [
                'param1' => 'value1',
            ],
            messageId: 'message_id',
            actorId: 'actor_id'
        );

        $updatedWatchFile = ($this->handler)($action);
        $sources = $updatedWatchFile->getSources();

        $this->assertCount(1, $sources);
        $source = $sources->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);

        $addedByMessage = $source->getAddedByMessage();
        $this->assertNotNull($addedByMessage);
        $this->assertInstanceOf(Message::class, $addedByMessage);
        $this->assertEquals('message_id', $addedByMessage->getId());
    }

    public function testAddSourceWithInvalidMessageContent(): void
    {
        $user = new User(id: 'test-user-id', email: 'test@example.com', roles: ['ROLE_USER'], userName: 'testuser');
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, $user, 'createdBy');
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor_id');
        $this->actorGateway->save($actor);

        // Link the actor to the watchfile so the source can get the actor set
        $watchFile->addActor($actor, ActorType::SUPPLIER, null, 0.5, null);

        $action = new AddSourceAction(
            watchFileId: 'watch_file_id',
            name: 'Test Source',
            type: 'website',
            primaryDomain: 'test.com',
            url: 'https://test.com',
            query: 'test query',
            description: new TranslatedText('Description en français', 'Description in English'),
            relevance: new TranslatedText('Pertinent en français', 'Relevant in English'),
            parameters: [
                'param1' => 'value1',
            ],
            messageId: 'invalid-message-content-id',
            actorId: 'actor_id'
        );

        $updatedWatchFile = ($this->handler)($action);
        $sources = $updatedWatchFile->getSources();

        $this->assertCount(1, $sources);
        $source = $sources->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertEquals('Test Source', $source->getName());
        $this->assertNull($source->getAddedByMessage());
    }

    public function testWatchFileNotFoundThrowsException(): void
    {
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor_id');
        $this->actorGateway->save($actor);

        $action = new AddSourceAction(
            watchFileId: 'invalid-watchfile-id',
            name: 'Test Source',
            type: 'website',
            primaryDomain: 'test.com',
            url: 'https://test.com',
            query: 'test query',
            description: new TranslatedText('Description en français', 'Description in English'),
            relevance: new TranslatedText('Pertinent en français', 'Relevant in English'),
            parameters: [
                'param1' => 'value1',
            ],
            actorId: 'actor_id'
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        ($this->handler)($action);
    }

    public function testAddSourceWithActorNotFound(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $action = new AddSourceAction(
            watchFileId: 'watch_file_id',
            name: 'Test Source',
            type: 'website',
            primaryDomain: 'test.com',
            url: 'https://test.com',
            query: 'test query',
            description: new TranslatedText('Description en français', 'Description in English'),
            relevance: new TranslatedText('Pertinent en français', 'Relevant in English'),
            parameters: [
                'param1' => 'value1',
            ],
            actorId: 'invalid-actor-id'
        );

        // Should not throw exception, just log warning and continue without actor
        $updatedWatchFile = ($this->handler)($action);
        $sources = $updatedWatchFile->getSources();

        $this->assertCount(1, $sources);
        $source = $sources->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertNull($source->getActor());
    }

    public function testAddSourceWithActorNotInWatchFile(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor_id');
        $this->actorGateway->save($actor);

        // Actor exists but is not linked to watchfile, so source should not have actor set

        $action = new AddSourceAction(
            watchFileId: 'watch_file_id',
            name: 'Test Source',
            type: 'website',
            primaryDomain: 'test.com',
            url: 'https://test.com',
            query: 'test query',
            description: new TranslatedText('Description en français', 'Description in English'),
            relevance: new TranslatedText('Pertinent en français', 'Relevant in English'),
            parameters: [
                'param1' => 'value1',
            ],
            actorId: 'actor_id'
        );

        // Actor exists but is not linked to watchfile, so source should not have actor set
        $updatedWatchFile = ($this->handler)($action);
        $sources = $updatedWatchFile->getSources();

        $this->assertCount(1, $sources);
        $source = $sources->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertNull($source->getActor());
    }

    public function testAddSourceWithActorInWatchFile(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor_id');
        $this->actorGateway->save($actor);

        // Create a watchfile actor link using the correct method
        $watchFile->addActor($actor, ActorType::SUPPLIER, null, 0.5, null);

        $action = new AddSourceAction(
            watchFileId: 'watch_file_id',
            name: 'Test Source',
            type: 'website',
            primaryDomain: 'test.com',
            url: 'https://test.com',
            query: 'test query',
            description: new TranslatedText('Description en français', 'Description in English'),
            relevance: new TranslatedText('Pertinent en français', 'Relevant in English'),
            parameters: [
                'param1' => 'value1',
            ],
            actorId: 'actor_id'
        );

        // Actor is linked to watchfile, so source should have actor set
        $updatedWatchFile = ($this->handler)($action);
        $sources = $updatedWatchFile->getSources();

        $this->assertCount(1, $sources);
        $source = $sources->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertSame($actor, $source->getActor());
    }

    public function testAddSourceAlreadyExists(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor_id');
        $this->actorGateway->save($actor);

        // Link the actor to the watchfile
        $watchFile->addActor($actor, ActorType::SUPPLIER, null, 0.5, null);

        // Manually create and save a source to the NullSourceGateway to simulate an existing source
        $existingSource = new Source(
            name: 'Existing Source',
            description: new TranslatedText('Description en français', 'Description in English'),
            type: SourceType::WEBSITE,
            url: 'https://test.com',
            primaryDomain: 'test.com',
            relevance: new TranslatedText('Pertinent en français', 'Relevant in English'),
            actor: null,
            watchFile: $watchFile,
            query: 'test query',
            parameters: [
                'param1' => 'value1',
            ]
        );
        $this->sourceGateway->save($existingSource);

        // Now try to add the same source again
        $action = new AddSourceAction(
            watchFileId: 'watch_file_id',
            name: 'Second Source',
            type: 'website',
            primaryDomain: 'test.com',
            url: 'https://test.com',
            query: 'test query',
            description: new TranslatedText('Description en français', 'Description in English'),
            relevance: new TranslatedText('Pertinent en français', 'Relevant in English'),
            parameters: [
                'param1' => 'value1',
            ],
            actorId: 'actor_id'
        );

        // Source already exists, should return watchfile without adding new source
        $updatedWatchFile = ($this->handler)($action);
        $sources = $updatedWatchFile->getSources();

        $this->assertCount(0, $sources);
    }

    public function testAddSourceWithoutActorId(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor_id');
        $this->actorGateway->save($actor);

        $action = new AddSourceAction(
            watchFileId: 'watch_file_id',
            name: 'Test Source',
            type: 'website',
            primaryDomain: 'test.com',
            url: 'https://test.com',
            query: 'test query',
            description: new TranslatedText('Description en français', 'Description in English'),
            relevance: new TranslatedText('Pertinent en français', 'Relevant in English'),
            parameters: [
                'param1' => 'value1',
            ],
            actorId: null
        );

        $updatedWatchFile = ($this->handler)($action);
        $sources = $updatedWatchFile->getSources();

        $this->assertCount(1, $sources);
        $source = $sources->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertNull($source->getActor());
    }

    public function testAddSourceThrowsExceptionWhenQuotaExceeded(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Add 2 existing sources to the watchfile
        $existingSource1 = new Source(
            name: 'Existing Source 1',
            description: new TranslatedText('Description en français', 'Description in English'),
            type: SourceType::WEBSITE,
            url: 'https://existing1.com',
            primaryDomain: 'existing1.com',
            relevance: new TranslatedText('Pertinent en français', 'Relevant in English'),
            actor: null,
            watchFile: $watchFile,
            query: null,
            parameters: []
        );
        $watchFile->addSource($existingSource1);
        $this->sourceGateway->save($existingSource1);

        $existingSource2 = new Source(
            name: 'Existing Source 2',
            description: new TranslatedText('Description en français', 'Description in English'),
            type: SourceType::WEBSITE,
            url: 'https://existing2.com',
            primaryDomain: 'existing2.com',
            relevance: new TranslatedText('Pertinent en français', 'Relevant in English'),
            actor: null,
            watchFile: $watchFile,
            query: null,
            parameters: []
        );
        $watchFile->addSource($existingSource2);
        $this->sourceGateway->save($existingSource2);

        // Create a new handler with a quota limit of 2 sources (non-inclusive)
        $usageLimitConfig = $this->createStub(UsageLimitConfigInterface::class);
        $usageLimitConfig
            ->method('sourceMaxPerWatchFile')
            ->willReturn(QuotaLimit::fromNullableInt(2, inclusive: false));

        $realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);
        $domainMatcher = new DomainMatcher();

        $handler = new AddSourceHandler(
            $this->messageGateway,
            $this->sourceGateway,
            $this->actorGateway,
            $this->watchFileActorGateway,
            $usageLimitConfig,
            $this->eventDispatcher,
            $realTimeUpdatePublisher,
            $domainMatcher,
            $this->logger
        );
        $handler->setWatchFileGateway($this->watchFileGateway);

        // Try to add a third source, which should exceed the quota
        $action = new AddSourceAction(
            watchFileId: 'watch_file_id',
            name: 'Third Source',
            type: 'website',
            primaryDomain: 'test.com',
            url: 'https://test.com',
            query: 'test query',
            description: new TranslatedText('Description en français', 'Description in English'),
            relevance: new TranslatedText('Pertinent en français', 'Relevant in English'),
            parameters: [
                'param1' => 'value1',
            ],
            actorId: null
        );

        $this->expectException(QuotaExceededException::class);
        $this->expectExceptionMessage('quota.source_max_per_watchfile');

        $handler($action);
    }

    public function testAddSourceSucceedsWhenQuotaNotExceeded(): void
    {
        $watchFile = new WatchFile('watchfile-name', 'watchfile-objective', new Organisation(
            'Test Org',
            'test-org-id'
        ));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        // Add 1 existing source
        $existingSource = new Source(
            name: 'Existing Source',
            description: new TranslatedText('Description en français', 'Description in English'),
            type: SourceType::WEBSITE,
            url: 'https://existing.com',
            primaryDomain: 'existing.com',
            relevance: new TranslatedText('Pertinent en français', 'Relevant in English'),
            actor: null,
            watchFile: $watchFile,
            query: null,
            parameters: []
        );
        $watchFile->addSource($existingSource);
        $this->sourceGateway->save($existingSource);

        // Create a new handler with a quota limit of 5 sources
        $usageLimitConfig = $this->createStub(UsageLimitConfigInterface::class);
        $usageLimitConfig
            ->method('sourceMaxPerWatchFile')
            ->willReturn(QuotaLimit::fromNullableInt(5, inclusive: false));

        $realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);
        $domainMatcher = new DomainMatcher();

        $handler = new AddSourceHandler(
            $this->messageGateway,
            $this->sourceGateway,
            $this->actorGateway,
            $this->watchFileActorGateway,
            $usageLimitConfig,
            $this->eventDispatcher,
            $realTimeUpdatePublisher,
            $domainMatcher,
            $this->logger
        );
        $handler->setWatchFileGateway($this->watchFileGateway);

        // Try to add a second source, which should succeed
        $action = new AddSourceAction(
            watchFileId: 'watch_file_id',
            name: 'Second Source',
            type: 'website',
            primaryDomain: 'test.com',
            url: 'https://test.com',
            query: 'test query',
            description: new TranslatedText('Description en français', 'Description in English'),
            relevance: new TranslatedText('Pertinent en français', 'Relevant in English'),
            parameters: [
                'param1' => 'value1',
            ],
            actorId: null
        );

        $updatedWatchFile = $handler($action);
        $sources = $updatedWatchFile->getSources();

        $this->assertCount(2, $sources);
    }
}
