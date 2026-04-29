<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\NormalizerContext;
use App\Infrastructure\Collect\Apify\Normalizer\GoogleNewsNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GoogleNewsNormalizer::class)]
class GoogleNewsNormalizerTest extends TestCase
{
    private GoogleNewsNormalizer $normalizer;
    private NormalizerContext $context;

    protected function setUp(): void
    {
        $this->normalizer = new GoogleNewsNormalizer();
        $this->context = new NormalizerContext('lhotanova/google-news-scraper', 'dataset-123', 0);
    }

    public function testNormalizeReturnsNullWhenUrlMissing(): void
    {
        // Arrange
        $item = [
            'title' => 'Some Title',
            'description' => 'Some long enough description here',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNull($result);
    }

    public function testNormalizeReturnsNullWhenExcerptTooShort(): void
    {
        // Arrange
        $item = [
            'url' => 'https://example.com/article',
            'title' => 'Title',
            'description' => 'Short', // less than 10 chars
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNull($result);
    }

    public function testNormalizeCreatesDocumentFromFullItem(): void
    {
        // Arrange
        $item = [
            'url' => 'https://example.com/article',
            'title' => 'Test Article Title',
            'description' => 'This is a full description of the article content.',
            'publishedAt' => '2024-01-15T10:00:00Z',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('https://example.com/article', $result->getUrl());
        $this->assertSame('Test Article Title', $result->getTitle());
        $this->assertSame('This is a full description of the article content.', $result->getContent());
        $this->assertSame('This is a full description of the article content.', $result->getExcerpt());
        $this->assertSame('2024-01-15', $result->getDatePublish()->format('Y-m-d'));
    }

    public function testNormalizeUsesLinkFallbackForUrl(): void
    {
        // Arrange
        $item = [
            'link' => 'https://news.example.com/story',
            'title' => 'News Story Title',
            'description' => 'This is a detailed description of the news story.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('https://news.example.com/story', $result->getUrl());
    }

    public function testNormalizeUsesSnippetFallbackForDescription(): void
    {
        // Arrange
        $item = [
            'url' => 'https://example.com/article',
            'title' => 'Test Article',
            'snippet' => 'This is the snippet content used as description fallback.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('This is the snippet content used as description fallback.', $result->getContent());
    }

    public function testNormalizeSetsCorrectProviderId(): void
    {
        // Arrange
        $url = 'https://example.com/news-article';
        $item = [
            'url' => $url,
            'title' => 'Provider ID Test',
            'description' => 'Description long enough to pass excerpt validation.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('apify:lhotanova/google-news-scraper:' . $url, $result->getProviderId());
    }

    public function testNormalizeUsesTitleAsContentWhenDescriptionEmpty(): void
    {
        // Arrange — url present but no description/snippet; title alone is used as content
        // However, buildExcerpt will be called with '' as description and title as fallback.
        // Since the title is >=10 chars, excerpt won't be null but comes from title.
        // And content = '' !== '' is false so content = title.
        $item = [
            'url' => 'https://example.com/article',
            'title' => 'A Title That Is Long Enough',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('A Title That Is Long Enough', $result->getContent());
    }

    public function testSupportsReturnsTrueForGoogleNewsActor(): void
    {
        // Act & Assert
        $this->assertTrue($this->normalizer->supports('lhotanova/google-news-scraper'));
    }

    public function testSupportsReturnsFalseForOtherActor(): void
    {
        // Act & Assert
        $this->assertFalse($this->normalizer->supports('apify/website-content-crawler'));
    }
}
