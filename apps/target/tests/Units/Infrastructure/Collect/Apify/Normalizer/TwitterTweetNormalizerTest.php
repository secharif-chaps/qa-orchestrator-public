<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\NormalizerContext;
use App\Infrastructure\Collect\Apify\Normalizer\TwitterTweetNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TwitterTweetNormalizer::class)]
class TwitterTweetNormalizerTest extends TestCase
{
    private TwitterTweetNormalizer $normalizer;
    private NormalizerContext $context;

    protected function setUp(): void
    {
        $this->normalizer = new TwitterTweetNormalizer();
        $this->context = new NormalizerContext('apidojo/tweet-scraper', 'dataset-123', 0);
    }

    public function testSupportsCorrectActorType(): void
    {
        // Arrange & Act & Assert
        $this->assertTrue($this->normalizer->supports('apidojo/tweet-scraper'));
    }

    public function testSupportsRejectWrongActorType(): void
    {
        // Arrange & Act & Assert
        $this->assertFalse($this->normalizer->supports('apify/website-content-crawler'));
    }

    public function testNormalizeValidTweet(): void
    {
        // Arrange
        $item = [
            'url' => 'https://twitter.com/user/status/123456789',
            'text' => 'This is a sample tweet with some content that is longer than 80 characters.',
            'user' => [
                'name' => 'John Doe',
                'screenName' => 'johndoe',
            ],
            'createdAt' => '2024-01-15T10:00:00Z',
            'retweetCount' => 42,
            'likeCount' => 156,
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($document);
        $this->assertStringContainsString('John Doe', $document->getTitle());
        $this->assertEquals('https://twitter.com/user/status/123456789', $document->getUrl());
        $this->assertStringContainsString('This is a sample tweet', $document->getContent());
        $providerId = $document->getProviderId();
        $this->assertNotNull($providerId);
        $this->assertStringStartsWith('apify:apidojo/tweet-scraper:', $providerId);
    }

    public function testNormalizeMissingUrl(): void
    {
        // Arrange
        $item = [
            'text' => 'Tweet without URL',
            'user' => [
                'name' => 'John',
            ],
            'createdAt' => '2024-01-15T10:00:00Z',
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNull($document);
    }

    public function testNormalizeMissingText(): void
    {
        // Arrange
        $item = [
            'url' => 'https://twitter.com/user/status/123456789',
            'user' => [
                'name' => 'John',
            ],
            'createdAt' => '2024-01-15T10:00:00Z',
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNull($document);
    }

    public function testNormalizeUrlFallback(): void
    {
        // Arrange
        $item = [
            'link' => 'https://twitter.com/user/status/fallback',
            'text' => 'Tweet with fallback URL field longer than ten',
            'user' => [
                'name' => 'John',
            ],
            'createdAt' => '2024-01-15T10:00:00Z',
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($document);
        $this->assertEquals('https://twitter.com/user/status/fallback', $document->getUrl());
    }

    public function testNormalizeExcerptTooShort(): void
    {
        // Arrange
        $item = [
            'url' => 'https://twitter.com/user/status/123456789',
            'text' => 'Short',
            'user' => [
                'name' => 'John',
            ],
            'createdAt' => '2024-01-15T10:00:00Z',
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNull($document, 'Excerpt must be at least 10 characters');
    }

    public function testNormalizeEngagementMetrics(): void
    {
        // Arrange
        $item = [
            'url' => 'https://twitter.com/user/status/123456789',
            'text' => 'Popular tweet with high engagement metrics here',
            'user' => [
                'name' => 'John Doe',
                'screenName' => 'johndoe',
            ],
            'createdAt' => '2024-01-15T10:00:00Z',
            'retweetCount' => 500,
            'likeCount' => 1200,
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($document);
        $this->assertEquals('https://twitter.com/user/status/123456789', $document->getUrl());
    }

    public function testNormalizeDateFallback(): void
    {
        // Arrange
        $item = [
            'url' => 'https://twitter.com/user/status/123456789',
            'text' => 'Tweet with date fallback field longer than ten',
            'user' => [
                'name' => 'John',
            ],
            'publishedAt' => '2024-01-15T10:00:00Z',
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($document);
        $this->assertInstanceOf(\DateTimeImmutable::class, $document->getDatePublish());
    }

    public function testNormalizeMinimalTweet(): void
    {
        // Arrange
        $item = [
            'url' => 'https://twitter.com/user/status/123456789',
            'text' => 'Minimal tweet with just basic required fields',
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($document);
        $this->assertEquals('https://twitter.com/user/status/123456789', $document->getUrl());
        $this->assertStringContainsString('Minimal tweet', $document->getContent());
    }

    public function testNormalizeTextFallback(): void
    {
        // Arrange
        $item = [
            'url' => 'https://twitter.com/user/status/123456789',
            'content' => 'Tweet content via fallback field longer than',
            'user' => [
                'name' => 'Alice',
            ],
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($document);
        $this->assertStringContainsString('Alice', $document->getTitle());
    }
}
