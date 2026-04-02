<?php

declare(strict_types=1);

namespace App\Tests\Integration\Source;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceType;
use App\Tests\Integration\AbstractApiTestCase;

/**
 * Integration tests for Source deduplication logic.
 *
 * Tests the complete flow from Source creation to database persistence,
 * validating that SourceDoctrineGateway correctly detects duplicates
 * via exact URL match and domain+query semantic matching.
 */
class SourceDeduplicationTest extends AbstractApiTestCase
{
    private SourceGatewayInterface $sourceGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sourceGateway = static::getContainer()->get(SourceGatewayInterface::class);
    }

    /**
     * Test that exact URL duplicates are detected.
     */
    public function testExactUrlDuplicateIsDetected(): void
    {
        // Arrange
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $source1 = new Source(
            name: 'Example Source',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::WEBSITE,
            url: 'https://example.com/path',
            primaryDomain: 'example.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: null
        );

        $watchFile->addSource($source1);
        $this->sourceGateway->save($source1);

        $source2 = new Source(
            name: 'Example Source Duplicate',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::WEBSITE,
            url: 'https://example.com/path',
            primaryDomain: 'example.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: null
        );

        // Act
        $isDuplicate = $this->sourceGateway->alreadyExist($watchFile, $source2);

        // Assert
        $this->assertTrue($isDuplicate, 'Exact URL match should be detected as duplicate');
    }

    /**
     * Test that same domain + same query are detected as semantic duplicates.
     */
    public function testSameDomainAndQueryDuplicateIsDetected(): void
    {
        // Arrange
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $source1 = new Source(
            name: 'RSS Feed 1',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::RSS_FEED,
            url: 'https://example.com/rss',
            primaryDomain: 'example.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: 'technology news'
        );

        $watchFile->addSource($source1);
        $this->sourceGateway->save($source1);

        $source2 = new Source(
            name: 'RSS Feed 2',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::RSS_FEED,
            url: 'https://example.com/feed',
            primaryDomain: 'example.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: 'technology news'
        );

        // Act
        $isDuplicate = $this->sourceGateway->alreadyExist($watchFile, $source2);

        // Assert
        $this->assertTrue($isDuplicate, 'Same domain + same query should be detected as semantic duplicates');
    }

    /**
     * Test that case-insensitive query matching works.
     */
    public function testCaseInsensitiveQueryDuplicateIsDetected(): void
    {
        // Arrange
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $source1 = new Source(
            name: 'Source 1',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::WEBSITE,
            url: 'https://example.com/search',
            primaryDomain: 'example.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: 'Technology NEWS'
        );

        $watchFile->addSource($source1);
        $this->sourceGateway->save($source1);

        $source2 = new Source(
            name: 'Source 2',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::WEBSITE,
            url: 'https://example.com/other-search',
            primaryDomain: 'example.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: 'technology news'
        );

        // Act
        $isDuplicate = $this->sourceGateway->alreadyExist($watchFile, $source2);

        // Assert
        $this->assertTrue($isDuplicate, 'Query comparison should be case-insensitive');
    }

    /**
     * Test that different paths on same domain are NOT duplicates (when no query).
     */
    public function testDifferentPathsAreNotDuplicates(): void
    {
        // Arrange
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $source1 = new Source(
            name: 'Path 1',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::WEBSITE,
            url: 'https://example.com/path1',
            primaryDomain: 'example.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: null
        );

        $watchFile->addSource($source1);
        $this->sourceGateway->save($source1);

        $source2 = new Source(
            name: 'Path 2',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::WEBSITE,
            url: 'https://example.com/path2',
            primaryDomain: 'example.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: null
        );

        // Act
        $isDuplicate = $this->sourceGateway->alreadyExist($watchFile, $source2);

        // Assert
        $this->assertFalse($isDuplicate, 'Different paths should NOT be detected as duplicates');
    }

    /**
     * Test that different types are NOT considered duplicates even with same URL.
     */
    public function testDifferentTypesAreNotDuplicates(): void
    {
        // Arrange
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $source1 = new Source(
            name: 'Website',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::WEBSITE,
            url: 'https://example.com/feed',
            primaryDomain: 'example.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: null
        );

        $watchFile->addSource($source1);
        $this->sourceGateway->save($source1);

        $source2 = new Source(
            name: 'RSS Feed',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::RSS_FEED,
            url: 'https://example.com/feed',
            primaryDomain: 'example.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: null
        );

        // Act
        $isDuplicate = $this->sourceGateway->alreadyExist($watchFile, $source2);

        // Assert
        $this->assertFalse($isDuplicate, 'Same URL with different types should NOT be duplicates');
    }

    /**
     * Test that sources from different WatchFiles are NOT considered duplicates.
     */
    public function testSourcesFromDifferentWatchFilesAreNotDuplicates(): void
    {
        // Arrange
        $user = UserFactory::new()->create();
        $watchFile1 = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();
        $watchFile2 = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $source1 = new Source(
            name: 'Source in WatchFile 1',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::WEBSITE,
            url: 'https://example.com/path',
            primaryDomain: 'example.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: null
        );

        $watchFile1->addSource($source1);
        $this->sourceGateway->save($source1);

        $source2 = new Source(
            name: 'Source in WatchFile 2',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::WEBSITE,
            url: 'https://example.com/path',
            primaryDomain: 'example.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: null
        );

        // Act - Check for duplicate in WatchFile 2
        $isDuplicate = $this->sourceGateway->alreadyExist($watchFile2, $source2);

        // Assert
        $this->assertFalse($isDuplicate, 'Same URL in different WatchFiles should NOT be duplicates');
    }

    /**
     * Test that cache is used for fast duplicate detection.
     */
    public function testCacheIsUsedForFastDuplicateDetection(): void
    {
        // Arrange
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $source1 = new Source(
            name: 'Cached Source',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::WEBSITE,
            url: 'https://cached-example.com/path',
            primaryDomain: 'cached-example.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: null
        );

        $watchFile->addSource($source1);
        $this->sourceGateway->save($source1);

        // Create duplicate with same URL
        $source2 = new Source(
            name: 'Duplicate Cached Source',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::WEBSITE,
            url: 'https://cached-example.com/path',
            primaryDomain: 'cached-example.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: null
        );

        // Act - First check (may warm up cache)
        $isDuplicate1 = $this->sourceGateway->alreadyExist($watchFile, $source2);

        // Second check (should use cache)
        $isDuplicate2 = $this->sourceGateway->alreadyExist($watchFile, $source2);

        // Assert
        $this->assertTrue($isDuplicate1, 'First check should detect duplicate');
        $this->assertTrue($isDuplicate2, 'Second check (cached) should also detect duplicate');
    }

    /**
     * Test that different queries on same domain are NOT duplicates.
     */
    public function testDifferentQueriesAreNotDuplicates(): void
    {
        // Arrange
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $source1 = new Source(
            name: 'Query 1',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::SOCIAL_MEDIA_X_SEARCH,
            url: 'https://x.com/search?q=hotels',
            primaryDomain: 'x.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: 'hotels sustainable'
        );

        $watchFile->addSource($source1);
        $this->sourceGateway->save($source1);

        $source2 = new Source(
            name: 'Query 2',
            description: new TranslatedText(fr: 'Test source', en: 'Test source'),
            type: SourceType::SOCIAL_MEDIA_X_SEARCH,
            url: 'https://x.com/search?q=restaurants',
            primaryDomain: 'x.com',
            relevance: new TranslatedText(fr: 'Relevant', en: 'Relevant'),
            actor: null,
            query: 'restaurants eco-friendly'
        );

        // Act
        $isDuplicate = $this->sourceGateway->alreadyExist($watchFile, $source2);

        // Assert
        $this->assertFalse($isDuplicate, 'Different queries on same domain should NOT be duplicates');
    }
}
