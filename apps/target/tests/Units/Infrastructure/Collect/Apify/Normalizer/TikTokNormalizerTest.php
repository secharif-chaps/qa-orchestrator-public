<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\NormalizerContext;
use App\Infrastructure\Collect\Apify\Normalizer\TikTokNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TikTokNormalizer::class)]
class TikTokNormalizerTest extends TestCase
{
    private TikTokNormalizer $normalizer;
    private NormalizerContext $context;

    protected function setUp(): void
    {
        $this->normalizer = new TikTokNormalizer();
        $this->context = new NormalizerContext('clockworks/tiktok-scraper', 'dataset-456', 0);
    }

    public function testSupportsCorrectActorType(): void
    {
        // Arrange & Act & Assert
        $this->assertTrue($this->normalizer->supports('clockworks/tiktok-scraper'));
    }

    public function testSupportsRejectsWrongActorType(): void
    {
        // Arrange & Act & Assert
        $this->assertFalse($this->normalizer->supports('apidojo/tweet-scraper'));
    }

    public function testNormalizeValidVideo(): void
    {
        // Arrange
        $item = [
            'webVideoUrl' => 'https://www.tiktok.com/@user/video/123456789',
            'desc' => 'This is a TikTok video description with some interesting content about a topic.',
            'author' => [
                'nickname' => 'cooluser',
                'uniqueId' => 'cooluser',
            ],
            'createTime' => 1705312800,
            'diggCount' => 1500,
            'shareCount' => 300,
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($document);
        $this->assertStringContainsString('cooluser', $document->getTitle());
        $this->assertEquals('https://www.tiktok.com/@user/video/123456789', $document->getUrl());
        $this->assertStringContainsString('This is a TikTok video', $document->getContent());
        $providerId = $document->getProviderId();
        $this->assertNotNull($providerId);
        $this->assertStringStartsWith('apify:clockworks/tiktok-scraper:', $providerId);
    }

    public function testNormalizeMissingUrl(): void
    {
        // Arrange
        $item = [
            'desc' => 'A TikTok video without any URL field present in the data.',
            'author' => [
                'nickname' => 'user123',
            ],
            'createTime' => 1705312800,
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNull($document);
    }

    public function testNormalizeMissingDesc(): void
    {
        // Arrange
        $item = [
            'webVideoUrl' => 'https://www.tiktok.com/@user/video/123456789',
            'author' => [
                'nickname' => 'user123',
            ],
            'createTime' => 1705312800,
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNull($document);
    }

    public function testNormalizeDescTooShort(): void
    {
        // Arrange
        $item = [
            'webVideoUrl' => 'https://www.tiktok.com/@user/video/123456789',
            'desc' => 'Short',
            'author' => [
                'nickname' => 'user123',
            ],
            'createTime' => 1705312800,
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNull($document, 'Excerpt must be at least 10 characters');
    }

    public function testNormalizeUrlFallback(): void
    {
        // Arrange — webVideoUrl absent, falls back to videoUrl then url then link
        $item = [
            'videoUrl' => 'https://www.tiktok.com/@user/video/fallback',
            'desc' => 'A TikTok video description with fallback URL field for testing purposes.',
            'author' => [
                'nickname' => 'user123',
            ],
            'createTime' => 1705312800,
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($document);
        $this->assertEquals('https://www.tiktok.com/@user/video/fallback', $document->getUrl());
    }

    public function testNormalizeUnixTimestamp(): void
    {
        // Arrange — createTime is a Unix timestamp integer
        $unixTimestamp = 1705312800; // 2024-01-15 10:00:00 UTC
        $item = [
            'webVideoUrl' => 'https://www.tiktok.com/@user/video/123456789',
            'desc' => 'A TikTok video with a Unix timestamp createTime field for date testing.',
            'author' => [
                'nickname' => 'user123',
            ],
            'createTime' => $unixTimestamp,
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($document);
        $this->assertInstanceOf(\DateTimeImmutable::class, $document->getDatePublish());
        $this->assertEquals($unixTimestamp, $document->getDatePublish()->getTimestamp());
    }

    public function testNormalizeAuthorNickname(): void
    {
        // Arrange
        $item = [
            'webVideoUrl' => 'https://www.tiktok.com/@techguru/video/987654321',
            'desc' => 'Amazing tech content shared by this creator on the platform today.',
            'author' => [
                'nickname' => 'techguru',
                'uniqueId' => 'techguru',
            ],
            'createTime' => 1705312800,
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($document);
        $this->assertStringContainsString('techguru', $document->getTitle());
    }

    public function testNormalizeMinimalVideo(): void
    {
        // Arrange — only URL and desc, no author, no date
        $item = [
            'webVideoUrl' => 'https://www.tiktok.com/@user/video/minimal',
            'desc' => 'Minimal TikTok video with only the required fields present.',
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($document);
        $this->assertEquals('https://www.tiktok.com/@user/video/minimal', $document->getUrl());
        $this->assertStringContainsString('Minimal TikTok video', $document->getContent());
        // When no author, title falls back to 'TikTok'
        $this->assertStringContainsString('TikTok', $document->getTitle());
    }

    public function testNormalizeProviderIdFormat(): void
    {
        // Arrange
        $videoUrl = 'https://www.tiktok.com/@user/video/123456789';
        $item = [
            'webVideoUrl' => $videoUrl,
            'desc' => 'Testing provider ID format for TikTok normalizer integration with Apify.',
            'author' => [
                'nickname' => 'user123',
            ],
            'createTime' => 1705312800,
        ];

        // Act
        $document = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($document);
        $providerId = $document->getProviderId();
        $this->assertNotNull($providerId);
        $this->assertEquals(\sprintf('apify:clockworks/tiktok-scraper:%s', $videoUrl), $providerId);
    }
}
