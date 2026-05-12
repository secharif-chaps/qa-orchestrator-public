<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\NormalizerContext;
use App\Infrastructure\Collect\Apify\Normalizer\YouTubeVideoNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(YouTubeVideoNormalizer::class)]
class YouTubeVideoNormalizerTest extends TestCase
{
    private YouTubeVideoNormalizer $normalizer;
    private NormalizerContext $context;

    protected function setUp(): void
    {
        $this->normalizer = new YouTubeVideoNormalizer();
        $this->context = new NormalizerContext('streamers/youtube-scraper', 'dataset-789', 0);
    }

    public function testSupportsCorrectActorType(): void
    {
        $this->assertTrue($this->normalizer->supports('streamers/youtube-scraper'));
    }

    public function testSupportsRejectsWrongActorType(): void
    {
        $this->assertFalse($this->normalizer->supports('apidojo/tweet-scraper'));
    }

    public function testNormalizeValidVideo(): void
    {
        $item = [
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'title' => 'Amazing Tech Review 2024',
            'description' => 'In this video we review the latest technology products available on the market this year.',
            'channelName' => 'TechChannel',
            'uploadDate' => '2024-01-15T10:00:00Z',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $this->assertStringContainsString('TechChannel', $document->getTitle());
        $this->assertStringContainsString('Amazing Tech Review', $document->getTitle());
        $this->assertEquals('https://www.youtube.com/watch?v=abc123', $document->getUrl());
        $this->assertStringContainsString('latest technology', $document->getContent());
        $providerId = $document->getProviderId();
        $this->assertNotNull($providerId);
        $this->assertStringStartsWith('apify:streamers/youtube-scraper:', $providerId);
    }

    public function testNormalizeMissingUrl(): void
    {
        $item = [
            'title' => 'A Video Without URL',
            'description' => 'This video item is missing the URL field entirely for testing.',
            'channelName' => 'SomeChannel',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNull($document);
    }

    public function testNormalizeMissingDescription(): void
    {
        $item = [
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'title' => 'Video Without Description',
            'channelName' => 'SomeChannel',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNull($document);
    }

    public function testNormalizeDescriptionTooShort(): void
    {
        $item = [
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'title' => 'Video With Short Description',
            'description' => 'Short',
            'channelName' => 'SomeChannel',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNull($document, 'Excerpt must be at least 10 characters');
    }

    public function testNormalizeUrlFallback(): void
    {
        // videoUrl fallback when url is absent
        $item = [
            'videoUrl' => 'https://www.youtube.com/watch?v=fallback456',
            'title' => 'Fallback URL Test Video',
            'description' => 'This video tests the URL fallback chain from videoUrl to url field.',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $this->assertEquals('https://www.youtube.com/watch?v=fallback456', $document->getUrl());
    }

    public function testNormalizeChannelNameInTitle(): void
    {
        $item = [
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'title' => 'My Great Video',
            'description' => 'A great video with channel name present for title prefix testing here.',
            'channelName' => 'GreatChannel',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $this->assertStringStartsWith('GreatChannel — ', $document->getTitle());
    }

    public function testNormalizeMissingChannelName(): void
    {
        // No channel — title is just the video title without prefix
        $item = [
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'title' => 'Standalone Video Title',
            'description' => 'A video without any channel name field for testing the title fallback.',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $this->assertStringNotContainsString(' — ', $document->getTitle());
        $this->assertStringContainsString('Standalone Video Title', $document->getTitle());
    }

    public function testNormalizeMinimalVideo(): void
    {
        // Only required fields: url + description
        $item = [
            'url' => 'https://www.youtube.com/watch?v=minimal',
            'description' => 'Minimal video with only the required fields for normalization testing.',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $this->assertEquals('https://www.youtube.com/watch?v=minimal', $document->getUrl());
        $this->assertStringContainsString('Minimal video', $document->getContent());
    }

    public function testNormalizeProviderIdFormat(): void
    {
        $videoUrl = 'https://www.youtube.com/watch?v=abc123';
        $item = [
            'url' => $videoUrl,
            'title' => 'Provider ID Format Test Video',
            'description' => 'Testing the provider ID format generated by the YouTube video normalizer.',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $providerId = $document->getProviderId();
        $this->assertNotNull($providerId);
        $this->assertEquals(\sprintf('apify:streamers/youtube-scraper:%s', $videoUrl), $providerId);
    }

    public function testNormalizeDateFallback(): void
    {
        $item = [
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'description' => 'A video with a publishedAt date field to test the date fallback chain.',
            'publishedAt' => '2024-03-20T14:30:00Z',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $this->assertInstanceOf(\DateTimeImmutable::class, $document->getDatePublish());
    }

    public function testNormalizeMissingDateFallsToCollectDate(): void
    {
        $before = new \DateTimeImmutable();
        $item = [
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'description' => 'A video with no date field — publish date should fall back to collect date.',
        ];

        $document = $this->normalizer->normalize($item, $this->context);
        $after = new \DateTimeImmutable();

        $this->assertNotNull($document);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $document->getDatePublish()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $document->getDatePublish()->getTimestamp());
    }
}
