<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\NormalizerContext;
use App\Infrastructure\Collect\Apify\Normalizer\FacebookAdNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FacebookAdNormalizer::class)]
final class FacebookAdNormalizerTest extends TestCase
{
    private FacebookAdNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new FacebookAdNormalizer();
    }

    public function testSupportsCorrectActorType(): void
    {
        self::assertTrue($this->normalizer->supports('apify/facebook-ads-scraper'));
    }

    public function testRejectsWrongActorType(): void
    {
        self::assertFalse($this->normalizer->supports('apify/facebook-posts-scraper'));
    }

    public function testNormalizeValidAd(): void
    {
        $item = [
            'ad_creative_body' => 'Buy our new product today! Limited offer for web scrapers.',
            'ad_snapshot_url' => 'https://ads-facebook.com/snapshot/abc123',
            'page_name' => 'TechProduct Inc',
            'ad_delivery_start_time' => '2026-05-01T08:00:00Z',
            'publisher_platforms' => ['facebook', 'instagram'],
            'impressions' => 15000,
            'spend' => '$500',
            'ad_library_url' => 'https://facebook.com/ads/library/ad/123456',
        ];

        $context = new NormalizerContext('apify/facebook-ads-scraper', 'dataset123', 0);
        $document = $this->normalizer->normalize($item, $context);

        self::assertNotNull($document);
        self::assertStringContainsString('TechProduct Inc', $document->getTitle());
        self::assertStringContainsString('Buy our new product', $document->getTitle());
        self::assertEquals('Buy our new product today! Limited offer for web scrapers.', $document->getContent());
        self::assertEquals('https://ads-facebook.com/snapshot/abc123', $document->getUrl());
        self::assertEquals(new \DateTimeImmutable('2026-05-01T08:00:00Z'), $document->getDatePublish());
        $providerId = $document->getProviderId();
        self::assertNotNull($providerId);
        self::assertStringContainsString('apify:', $providerId);

        // Verify ad-specific metadata
        $metadata = $document->getMetadata();
        self::assertArrayHasKey('impressions', $metadata);
        self::assertEquals(15000, $metadata['impressions']);
        self::assertArrayHasKey('publisher_platforms', $metadata);
        self::assertEquals('facebook, instagram', $metadata['publisher_platforms']);
    }

    public function testReturnsNullWhenMissingAdText(): void
    {
        $item = [
            'ad_snapshot_url' => 'https://ads-facebook.com/snapshot/abc123',
            'page_name' => 'TechProduct Inc',
        ];

        $context = new NormalizerContext('apify/facebook-ads-scraper', 'dataset123', 0);
        $document = $this->normalizer->normalize($item, $context);

        self::assertNull($document);
    }

    public function testReturnsNullWhenMissingAdUrl(): void
    {
        $item = [
            'ad_creative_body' => 'Buy our product today!',
            'page_name' => 'TechProduct Inc',
        ];

        $context = new NormalizerContext('apify/facebook-ads-scraper', 'dataset123', 0);
        $document = $this->normalizer->normalize($item, $context);

        self::assertNull($document);
    }

    public function testNormalizeWithFallbackFieldNames(): void
    {
        $item = [
            'creative_body' => 'Ad via fallback creative field',
            'snapshot_url' => 'https://ads-facebook.com/snapshot/def456',
            'advertiser_name' => 'Brand X',
            'start_date' => '2026-04-28T12:00:00Z',
            'platforms' => 'Facebook',
        ];

        $context = new NormalizerContext('apify/facebook-ads-scraper', 'dataset123', 0);
        $document = $this->normalizer->normalize($item, $context);

        self::assertNotNull($document);
        self::assertStringContainsString('Brand X', $document->getTitle());
        self::assertEquals('Ad via fallback creative field', $document->getContent());
        self::assertEquals('https://ads-facebook.com/snapshot/def456', $document->getUrl());
        self::assertEquals(new \DateTimeImmutable('2026-04-28T12:00:00Z'), $document->getDatePublish());
        $providerId = $document->getProviderId();
        self::assertNotNull($providerId);
        self::assertStringContainsString('apify:', $providerId);

        $metadata = $document->getMetadata();
        self::assertArrayHasKey('publisher_platforms', $metadata);
        self::assertEquals('Facebook', $metadata['publisher_platforms']);
    }
}
