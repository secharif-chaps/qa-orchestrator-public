<?php

declare(strict_types=1);

namespace App\Tests\Integration\WatchFile\Source;

use App\Application\WatchFile\Source\AddSourceAction;
use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileActorFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Actor\ActorType;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Integration\AbstractApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

/**
 * Integration tests for AddSourceHandler via MessageBus.
 *
 * These tests verify the complete flow:
 * 1. Dispatch action to MessageBus
 * 2. Message is routed to correct transport (async_priority_high)
 * 3. Message is processed by handler
 * 4. Database state is correct after processing
 */
class AddSourceHandlerTest extends AbstractApiTestCase
{
    use Factories;
    use InteractsWithMessenger;
    use ResetDatabase;
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this
            ->getContainer()
            ->get(EntityManagerInterface::class);
        $this->messageBus = $this->getContainer()
            ->get(MessageBusInterface::class);
    }

    private function refreshWatchFile(string $watchFileId): WatchFile
    {
        $this->entityManager->clear();
        $watchFile = $this->entityManager->find(WatchFile::class, $watchFileId);
        $this->assertNotNull($watchFile, 'WatchFile should exist');

        return $watchFile;
    }

    public function testDispatchAddSourceActionRoutesToAsyncPriorityHigh(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'TechCrunch RSS',
            type: SourceType::RSS_FEED->value,
            primaryDomain: 'techcrunch.com',
            url: 'https://techcrunch.com/feed/',
            query: '',
            description: new TranslatedText(fr: 'Flux RSS tech.', en: 'Tech RSS feed.'),
            relevance: new TranslatedText(fr: 'Source majeure.', en: 'Major source.')
        );

        $this->messageBus->dispatch($action);

        // Verify message was queued to async_priority_high
        $this->transport('async_priority_high')
            ->queue()
            ->assertContains(AddSourceAction::class);
        $this->transport('async_priority_high')
            ->queue()
            ->assertCount(1);
    }

    public function testProcessAddSourceCreatesNewSource(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Tech News RSS',
            type: SourceType::RSS_FEED->value,
            primaryDomain: 'technews.com',
            url: 'https://technews.com/feed.xml',
            query: '',
            description: new TranslatedText(
                fr: 'Flux RSS pour les actualités tech.',
                en: 'RSS feed for tech news.'
            ),
            relevance: new TranslatedText(
                fr: 'Très pertinent pour la veille technologique.',
                en: 'Very relevant for technology monitoring.'
            ),
            parameters: [
                'refresh_interval' => 3600,
            ]
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);
        $this->transport('async_priority_high')
            ->queue()
            ->assertEmpty();

        // Verify database state
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $sources = $updatedWatchFile->getSources();

        $this->assertCount(1, $sources);
        $source = $sources->first();
        $this->assertInstanceOf(Source::class, $source);
        $this->assertEquals('Tech News RSS', $source->getName());
        $this->assertEquals(SourceType::RSS_FEED, $source->getType());
        $this->assertEquals('https://technews.com/feed.xml', $source->getUrl());
        $this->assertEquals('technews.com', $source->getPrimaryDomain());
    }

    public function testProcessAddSourceWithActorLink(): void
    {
        $user = UserFactory::createOne();
        $actor = ActorFactory::createOne([
            'label' => 'TechCorp',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        // Link actor to watchfile first (required for source-actor linking)
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::COMPETITOR,
        ]);

        $watchFileId = $watchFile->getId();
        $actorId = $actor->getId();

        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'TechCorp LinkedIn',
            type: SourceType::SOCIAL_MEDIA_LINKEDIN_COMPANY->value,
            primaryDomain: 'linkedin.com',
            url: 'https://linkedin.com/company/techcorp',
            query: '',
            description: new TranslatedText(fr: 'Page LinkedIn.', en: 'LinkedIn page.'),
            relevance: new TranslatedText(fr: 'Source officielle.', en: 'Official source.'),
            actorId: $actorId
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        // Verify source is linked to actor
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $sources = $updatedWatchFile->getSources();
        $this->assertCount(1, $sources);

        $source = $sources->first();
        $this->assertInstanceOf(Source::class, $source);
        $this->assertNotNull($source->getActor());
        $this->assertEquals('TechCorp', $source->getActor()->getLabel());
    }

    public function testProcessDuplicateSourceIsIgnored(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        // Create existing source
        SourceFactory::createOne([
            'watchFile' => $watchFile,
            'name' => 'Existing Source',
            'type' => SourceType::WEBSITE,
            'url' => 'https://example.com',
            'primaryDomain' => 'example.com',
        ]);

        $watchFileId = $watchFile->getId();

        // Try to add duplicate (same URL and type)
        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Duplicate Source',
            type: SourceType::WEBSITE->value,
            primaryDomain: 'example.com',
            url: 'https://example.com',
            query: '',
            description: new TranslatedText(fr: 'Duplicate.', en: 'Duplicate.'),
            relevance: new TranslatedText(fr: 'Test.', en: 'Test.')
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        // Should still have only one source
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $sources = $updatedWatchFile->getSources();
        $this->assertCount(1, $sources);

        // Original name should be preserved
        $source = $sources->first();
        $this->assertInstanceOf(Source::class, $source);
        $this->assertEquals('Existing Source', $source->getName());
    }

    public function testProcessMultipleSourcesInBatch(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Dispatch batch of sources (simulating N8N workflow output)
        $sources = [
            [
                'name' => 'First RSS',
                'type' => SourceType::RSS_FEED->value,
                'url' => 'https://first.com/feed',
            ],
            [
                'name' => 'Second Blog',
                'type' => SourceType::BLOG->value,
                'url' => 'https://second.com/blog',
            ],
            [
                'name' => 'Third Website',
                'type' => SourceType::WEBSITE->value,
                'url' => 'https://third.com',
            ],
        ];

        foreach ($sources as $sourceData) {
            $action = new AddSourceAction(
                watchFileId: $watchFileId,
                name: $sourceData['name'],
                type: $sourceData['type'],
                primaryDomain: (string) parse_url($sourceData['url'], \PHP_URL_HOST),
                url: $sourceData['url'],
                query: '',
                description: new TranslatedText(fr: 'Description.', en: 'Description.'),
                relevance: new TranslatedText(fr: 'Pertinent.', en: 'Relevant.')
            );
            $this->messageBus->dispatch($action);
        }

        // Verify all queued
        $this->transport('async_priority_high')
            ->queue()
            ->assertCount(3);

        // Process all
        $this->transport('async_priority_high')
            ->process();
        $this->transport('async_priority_high')
            ->queue()
            ->assertEmpty();

        // Verify all created
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $allSources = $updatedWatchFile->getSources();
        $this->assertCount(3, $allSources);
    }

    public function testProcessTwitterSearchSource(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'AI News Twitter Search',
            type: SourceType::SOCIAL_MEDIA_X_SEARCH->value,
            primaryDomain: 'twitter.com',
            url: 'https://twitter.com/search',
            query: '#ArtificialIntelligence OR #MachineLearning',
            description: new TranslatedText(fr: 'Recherche Twitter sur l\'IA.', en: 'Twitter search for AI.'),
            relevance: new TranslatedText(fr: 'Source de veille temps réel.', en: 'Real-time monitoring source.'),
            parameters: [
                'language' => 'en',
                'result_type' => 'recent',
            ]
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $sources = $updatedWatchFile->getSources();
        $this->assertCount(1, $sources);

        $source = $sources->first();
        $this->assertInstanceOf(Source::class, $source);
        $this->assertEquals(SourceType::SOCIAL_MEDIA_X_SEARCH, $source->getType());
        $this->assertEquals('#ArtificialIntelligence OR #MachineLearning', $source->getQuery());
        $this->assertTrue($source->getType()->isSocialMedia());
    }

    public function testProcessYouTubeChannelSource(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Tech Review Channel',
            type: SourceType::VIDEO_YOUTUBE_CHANNEL->value,
            primaryDomain: 'youtube.com',
            url: 'https://youtube.com/c/TechReviewChannel',
            query: '',
            description: new TranslatedText(
                fr: 'Chaîne YouTube de revue tech.',
                en: 'Tech review YouTube channel.'
            ),
            relevance: new TranslatedText(
                fr: 'Analyse de produits concurrents.',
                en: 'Competitor product analysis.'
            )
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $sources = $updatedWatchFile->getSources();
        $this->assertCount(1, $sources);

        $source = $sources->first();
        $this->assertInstanceOf(Source::class, $source);
        $this->assertEquals(SourceType::VIDEO_YOUTUBE_CHANNEL, $source->getType());
    }

    public function testProcessSourceWithNonLinkedActorIsIgnored(): void
    {
        $user = UserFactory::createOne();
        $actor = ActorFactory::createOne([
            'label' => 'UnlinkedActor',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        // Actor is NOT linked to watchfile

        $watchFileId = $watchFile->getId();
        $actorId = $actor->getId();

        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Source With Unlinked Actor',
            type: SourceType::WEBSITE->value,
            primaryDomain: 'example.com',
            url: 'https://example.com',
            query: '',
            description: new TranslatedText(fr: 'Test.', en: 'Test.'),
            relevance: new TranslatedText(fr: 'Test.', en: 'Test.'),
            actorId: $actorId
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        // Source should be created but without actor link
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $sources = $updatedWatchFile->getSources();
        $this->assertCount(1, $sources);

        $source = $sources->first();
        $this->assertInstanceOf(Source::class, $source);
        $this->assertNull($source->getActor());
    }

    public function testProcessSourceWithNonExistentActorIdIsIgnored(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Source With Invalid Actor',
            type: SourceType::WEBSITE->value,
            primaryDomain: 'example.com',
            url: 'https://example.com',
            query: '',
            description: new TranslatedText(fr: 'Test.', en: 'Test.'),
            relevance: new TranslatedText(fr: 'Test.', en: 'Test.'),
            actorId: '00000000-0000-0000-0000-000000000000' // Non-existent
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        // Source should be created but without actor link
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $sources = $updatedWatchFile->getSources();
        $this->assertCount(1, $sources);

        $source = $sources->first();
        $this->assertInstanceOf(Source::class, $source);
        $this->assertNull($source->getActor());
    }

    public function testProcessSourceWithEmptyQuery(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM might send empty string for query
        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Empty Query Source',
            type: SourceType::WEBSITE->value,
            primaryDomain: 'example.com',
            url: 'https://example.com',
            query: '', // Empty
            description: new TranslatedText(fr: 'Test.', en: 'Test.'),
            relevance: new TranslatedText(fr: 'Test.', en: 'Test.')
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $sources = $updatedWatchFile->getSources();
        $this->assertCount(1, $sources);
    }

    public function testProcessSourceWithComplexTwitterQuery(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Complex Twitter search query from LLM
        $complexQuery = '(OpenAI OR Anthropic OR "Google DeepMind") (launch OR release OR announcement) -RT lang:en since:2024-01-01';

        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'AI Competitors Twitter',
            type: SourceType::SOCIAL_MEDIA_X_SEARCH->value,
            primaryDomain: 'twitter.com',
            url: 'https://twitter.com/search',
            query: $complexQuery,
            description: new TranslatedText(fr: 'Recherche Twitter.', en: 'Twitter search.'),
            relevance: new TranslatedText(fr: 'Veille concurrentielle.', en: 'Competitive intelligence.')
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $sources = $updatedWatchFile->getSources();
        $this->assertCount(1, $sources);

        $source = $sources->first();
        $this->assertNotFalse($source);
        $query = $source->getQuery();
        $this->assertNotNull($query);
        $this->assertStringContainsString('OpenAI OR Anthropic', $query);
    }

    public function testProcessFailsForInvalidSourceType(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Invalid source type (LLM hallucination)
        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Invalid Type Source',
            type: 'not_a_real_source_type',
            primaryDomain: 'example.com',
            url: 'https://example.com',
            query: '',
            description: new TranslatedText(fr: 'Test.', en: 'Test.'),
            relevance: new TranslatedText(fr: 'Test.', en: 'Test.')
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->queue()
            ->assertCount(1);

        // Processing should fail
        $this->expectException(\Exception::class);
        $this->transport('async_priority_high')
            ->throwExceptions()
            ->process(1);
    }

    public function testAutomaticActorLinkingExactDomainMatch(): void
    {
        $user = UserFactory::createOne();
        $actor = ActorFactory::createOne([
            'label' => 'Acme Corp',
            'primaryDomain' => 'acme.com',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        // Link actor to watchfile
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::COMPETITOR,
        ]);

        $watchFileId = $watchFile->getId();

        // Create source without actorId - should automatically link
        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Acme News Article',
            type: SourceType::WEBSITE->value,
            primaryDomain: 'acme.com',
            url: 'https://acme.com/news/article',
            query: '',
            description: new TranslatedText(fr: 'Article Acme.', en: 'Acme article.'),
            relevance: new TranslatedText(fr: 'Pertinent.', en: 'Relevant.'),
            actorId: null // No explicit actor
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        // Verify source is automatically linked to actor
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $sources = $updatedWatchFile->getSources();
        $this->assertCount(1, $sources);

        $source = $sources->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertNotNull($source->getActor(), 'Source should be automatically linked to actor');
        $this->assertEquals('Acme Corp', $source->getActor()->getLabel());
        $this->assertEquals('acme.com', $source->getActor()->getPrimaryDomain());
    }

    public function testAutomaticActorLinkingWwwPrefixMatchActorHasWww(): void
    {
        $user = UserFactory::createOne();
        $actor = ActorFactory::createOne([
            'label' => 'Acme Corp',
            'primaryDomain' => 'www.acme.com',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::COMPETITOR,
        ]);

        $watchFileId = $watchFile->getId();

        // Source has domain without www, actor has www - should match
        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Acme News',
            type: SourceType::WEBSITE->value,
            primaryDomain: 'acme.com',
            url: 'https://acme.com/news/article',
            query: '',
            description: new TranslatedText(fr: 'Article.', en: 'Article.'),
            relevance: new TranslatedText(fr: 'Pertinent.', en: 'Relevant.'),
            actorId: null
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $source = $updatedWatchFile->getSources()
->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertNotNull(
            $source->getActor(),
            'Source should be automatically linked despite www prefix difference'
        );
        $this->assertEquals('Acme Corp', $source->getActor()->getLabel());
    }

    public function testAutomaticActorLinkingWwwPrefixMatchSourceHasWww(): void
    {
        $user = UserFactory::createOne();
        $actor = ActorFactory::createOne([
            'label' => 'Acme Corp',
            'primaryDomain' => 'acme.com',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::COMPETITOR,
        ]);

        $watchFileId = $watchFile->getId();

        // Source has www, actor doesn't - should match
        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Acme News',
            type: SourceType::WEBSITE->value,
            primaryDomain: 'www.acme.com',
            url: 'https://www.acme.com/news/article',
            query: '',
            description: new TranslatedText(fr: 'Article.', en: 'Article.'),
            relevance: new TranslatedText(fr: 'Pertinent.', en: 'Relevant.'),
            actorId: null
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $source = $updatedWatchFile->getSources()
->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertNotNull(
            $source->getActor(),
            'Source should be automatically linked despite www prefix difference'
        );
        $this->assertEquals('Acme Corp', $source->getActor()->getLabel());
    }

    public function testAutomaticActorLinkingNoMatch(): void
    {
        $user = UserFactory::createOne();
        $actor1 = ActorFactory::createOne([
            'label' => 'Example Corp',
            'primaryDomain' => 'example.com',
        ]);
        $actor2 = ActorFactory::createOne([
            'label' => 'Test Org',
            'primaryDomain' => 'test.org',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor1,
            'type' => ActorType::COMPETITOR,
        ]);
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor2,
            'type' => ActorType::COMPETITOR,
        ]);

        $watchFileId = $watchFile->getId();

        // Source domain doesn't match any actor - should not link
        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Acme News',
            type: SourceType::WEBSITE->value,
            primaryDomain: 'acme.com',
            url: 'https://acme.com/news/article',
            query: '',
            description: new TranslatedText(fr: 'Article.', en: 'Article.'),
            relevance: new TranslatedText(fr: 'Pertinent.', en: 'Relevant.'),
            actorId: null
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $source = $updatedWatchFile->getSources()
->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertNull($source->getActor(), 'Source should not be linked when no domain match exists');
    }

    public function testAutomaticActorLinkingActorAlreadySpecified(): void
    {
        $user = UserFactory::createOne();
        $actor1 = ActorFactory::createOne([
            'label' => 'Acme Corp',
            'primaryDomain' => 'acme.com',
        ]);
        $actor2 = ActorFactory::createOne([
            'label' => 'Different Corp',
            'primaryDomain' => 'different.com',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor1,
            'type' => ActorType::COMPETITOR,
        ]);
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor2,
            'type' => ActorType::COMPETITOR,
        ]);

        $watchFileId = $watchFile->getId();
        $actor2Id = $actor2->getId();

        // Explicit actorId provided - should use it, not auto-link to actor1
        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Acme News',
            type: SourceType::WEBSITE->value,
            primaryDomain: 'acme.com', // Matches actor1 domain
            url: 'https://acme.com/news/article',
            query: '',
            description: new TranslatedText(fr: 'Article.', en: 'Article.'),
            relevance: new TranslatedText(fr: 'Pertinent.', en: 'Relevant.'),
            actorId: $actor2Id // Explicitly specify actor2
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $source = $updatedWatchFile->getSources()
->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertNotNull($source->getActor(), 'Source should be linked to explicitly provided actor');
        $this->assertEquals(
            'Different Corp',
            $source->getActor()
->getLabel(),
            'Should use explicitly provided actor, not auto-link'
        );
    }

    public function testAutomaticActorLinkingCaseInsensitive(): void
    {
        $user = UserFactory::createOne();
        $actor = ActorFactory::createOne([
            'label' => 'Acme Corp',
            'primaryDomain' => 'Acme.Com', // Mixed case
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::COMPETITOR,
        ]);

        $watchFileId = $watchFile->getId();

        // Source domain in different case - should still match
        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Acme News',
            type: SourceType::WEBSITE->value,
            primaryDomain: 'ACME.COM', // Uppercase
            url: 'https://ACME.COM/news/article',
            query: '',
            description: new TranslatedText(fr: 'Article.', en: 'Article.'),
            relevance: new TranslatedText(fr: 'Pertinent.', en: 'Relevant.'),
            actorId: null
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $source = $updatedWatchFile->getSources()
->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertNotNull($source->getActor(), 'Source should be automatically linked despite case difference');
        $this->assertEquals('Acme Corp', $source->getActor()->getLabel());
    }

    public function testAutomaticActorLinkingExtractsDomainFromUrl(): void
    {
        $user = UserFactory::createOne();
        $actor = ActorFactory::createOne([
            'label' => 'Acme Corp',
            'primaryDomain' => 'acme.com',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::COMPETITOR,
        ]);

        $watchFileId = $watchFile->getId();

        // Source with empty primaryDomain but valid URL - should extract from URL
        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Acme News',
            type: SourceType::WEBSITE->value,
            primaryDomain: '', // Empty - should extract from URL
            url: 'https://acme.com/news/article',
            query: '',
            description: new TranslatedText(fr: 'Article.', en: 'Article.'),
            relevance: new TranslatedText(fr: 'Pertinent.', en: 'Relevant.'),
            actorId: null
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $source = $updatedWatchFile->getSources()
->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertNotNull(
            $source->getActor(),
            'Source should be automatically linked using domain extracted from URL'
        );
        $this->assertEquals('Acme Corp', $source->getActor()->getLabel());
    }

    public function testAutomaticActorLinkingNoActorsInWatchFile(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // No actors in watchfile - should create source without linking
        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Acme News',
            type: SourceType::WEBSITE->value,
            primaryDomain: 'acme.com',
            url: 'https://acme.com/news/article',
            query: '',
            description: new TranslatedText(fr: 'Article.', en: 'Article.'),
            relevance: new TranslatedText(fr: 'Pertinent.', en: 'Relevant.'),
            actorId: null
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $source = $updatedWatchFile->getSources()
->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertNull($source->getActor(), 'Source should be created without actor link when no actors exist');
    }

    public function testAutomaticActorLinkingActorWithoutDomain(): void
    {
        $user = UserFactory::createOne();
        $actor = ActorFactory::createOne([
            'label' => 'Acme Corp',
            'primaryDomain' => null, // Actor has no domain
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::COMPETITOR,
        ]);

        $watchFileId = $watchFile->getId();

        // Actor has no domain - should not link
        $action = new AddSourceAction(
            watchFileId: $watchFileId,
            name: 'Acme News',
            type: SourceType::WEBSITE->value,
            primaryDomain: 'acme.com',
            url: 'https://acme.com/news/article',
            query: '',
            description: new TranslatedText(fr: 'Article.', en: 'Article.'),
            relevance: new TranslatedText(fr: 'Pertinent.', en: 'Relevant.'),
            actorId: null
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
            ->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $source = $updatedWatchFile->getSources()
->first();
        $this->assertNotFalse($source);
        $this->assertInstanceOf(Source::class, $source);
        $this->assertNull($source->getActor(), 'Source should not be linked when actor has no domain');
    }
}
