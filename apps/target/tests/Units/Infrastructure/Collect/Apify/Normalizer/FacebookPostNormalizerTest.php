<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\NormalizerContext;
use App\Infrastructure\Collect\Apify\Normalizer\FacebookPostNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FacebookPostNormalizer::class)]
final class FacebookPostNormalizerTest extends TestCase
{
    private FacebookPostNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new FacebookPostNormalizer();
    }

    public function testSupportsCorrectActorType(): void
    {
        self::assertTrue($this->normalizer->supports('apify/facebook-posts-scraper'));
    }

    public function testRejectsWrongActorType(): void
    {
        self::assertFalse($this->normalizer->supports('apify/twitter-scraper'));
    }

    public function testNormalizeValidPost(): void
    {
        $item = [
            'text' => 'This is a Facebook post about web scraping',
            'url' => 'https://facebook.com/posts/12345',
            'pageName' => 'Tech News',
            'timestamp' => '2026-05-05T10:00:00Z',
            'likes' => 42,
            'comments' => 5,
            'shares' => 2,
        ];

        $context = new NormalizerContext('apify/facebook-posts-scraper', 'dataset123', 0);
        $document = $this->normalizer->normalize($item, $context);

        self::assertNotNull($document);
        self::assertStringContainsString('Tech News', $document->getTitle());
        self::assertStringContainsString('This is a Facebook post', $document->getTitle());
        self::assertEquals('This is a Facebook post about web scraping', $document->getContent());
        self::assertEquals('https://facebook.com/posts/12345', $document->getUrl());
        self::assertEquals(new \DateTimeImmutable('2026-05-05T10:00:00Z'), $document->getDatePublish());
        $providerId = $document->getProviderId();
        self::assertNotNull($providerId);
        self::assertStringContainsString('apify:', $providerId);

        // Verify engagement metadata
        $metadata = $document->getMetadata();
        self::assertArrayHasKey('engagement', $metadata);
        self::assertEquals([
            'likes' => 42,
            'comments' => 5,
            'shares' => 2,
        ], $metadata['engagement']);
    }

    public function testReturnsNullWhenMissingText(): void
    {
        $item = [
            'url' => 'https://facebook.com/posts/12345',
            'pageName' => 'Tech News',
        ];

        $context = new NormalizerContext('apify/facebook-posts-scraper', 'dataset123', 0);
        $document = $this->normalizer->normalize($item, $context);

        self::assertNull($document);
    }

    public function testReturnsNullWhenMissingUrl(): void
    {
        $item = [
            'text' => 'This is a Facebook post',
            'pageName' => 'Tech News',
        ];

        $context = new NormalizerContext('apify/facebook-posts-scraper', 'dataset123', 0);
        $document = $this->normalizer->normalize($item, $context);

        self::assertNull($document);
    }

    public function testNormalizeWithFallbackFieldNames(): void
    {
        $item = [
            'content' => 'Post content via fallback field',
            'link' => 'https://facebook.com/posts/54321',
            'author' => 'News Page',
            'created_time' => '2026-05-04T15:30:00Z',
        ];

        $context = new NormalizerContext('apify/facebook-posts-scraper', 'dataset123', 0);
        $document = $this->normalizer->normalize($item, $context);

        self::assertNotNull($document);
        self::assertStringContainsString('News Page', $document->getTitle());
        self::assertEquals('Post content via fallback field', $document->getContent());
        self::assertEquals('https://facebook.com/posts/54321', $document->getUrl());
        $providerId = $document->getProviderId();
        self::assertNotNull($providerId);
    }
}
