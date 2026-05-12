<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\NormalizerContext;
use App\Infrastructure\Collect\Apify\Normalizer\YouTubeCommentNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(YouTubeCommentNormalizer::class)]
class YouTubeCommentNormalizerTest extends TestCase
{
    private YouTubeCommentNormalizer $normalizer;
    private NormalizerContext $context;

    protected function setUp(): void
    {
        $this->normalizer = new YouTubeCommentNormalizer();
        $this->context = new NormalizerContext('streamers/youtube-comments-scraper', 'dataset-comments-001', 3);
    }

    public function testSupportsCorrectActorType(): void
    {
        $this->assertTrue($this->normalizer->supports('streamers/youtube-comments-scraper'));
    }

    public function testSupportsRejectsWrongActorType(): void
    {
        $this->assertFalse($this->normalizer->supports('streamers/youtube-scraper'));
    }

    public function testNormalizeValidComment(): void
    {
        $item = [
            'id' => 'Ugx_abc123',
            'videoUrl' => 'https://www.youtube.com/watch?v=abc123',
            'text' => 'This is an excellent video about technology, highly recommend watching it!',
            'author' => 'JohnDoe',
            'publishedAt' => '2024-01-20T12:00:00Z',
            'likeCount' => 42,
            'replyCount' => 5,
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $this->assertStringContainsString('JohnDoe', $document->getTitle());
        $this->assertEquals('https://www.youtube.com/watch?v=abc123', $document->getUrl());
        $this->assertStringContainsString('excellent video', $document->getContent());
        $providerId = $document->getProviderId();
        $this->assertNotNull($providerId);
        $this->assertStringContainsString('Ugx_abc123', $providerId);
        $this->assertStringStartsWith('apify:streamers/youtube-comments-scraper:', $providerId);
    }

    public function testNormalizeMissingVideoUrl(): void
    {
        $item = [
            'id' => 'Ugx_abc123',
            'text' => 'A comment without any video URL field present in the item data.',
            'author' => 'JohnDoe',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNull($document);
    }

    public function testNormalizeMissingText(): void
    {
        $item = [
            'id' => 'Ugx_abc123',
            'videoUrl' => 'https://www.youtube.com/watch?v=abc123',
            'author' => 'JohnDoe',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNull($document);
    }

    public function testNormalizeTextTooShort(): void
    {
        $item = [
            'videoUrl' => 'https://www.youtube.com/watch?v=abc123',
            'text' => 'Short',
            'author' => 'JohnDoe',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNull($document, 'Excerpt must be at least 10 characters');
    }

    public function testNormalizeAuthorInTitle(): void
    {
        $item = [
            'videoUrl' => 'https://www.youtube.com/watch?v=abc123',
            'text' => 'Amazing content that I really enjoy watching on this channel every week!',
            'author' => 'SpecificAuthor',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $this->assertStringStartsWith('SpecificAuthor — ', $document->getTitle());
    }

    public function testNormalizeMissingAuthor(): void
    {
        // No author — falls back to 'YouTube'
        $item = [
            'videoUrl' => 'https://www.youtube.com/watch?v=abc123',
            'text' => 'A comment with no author field — should fall back to YouTube default.',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $this->assertStringStartsWith('YouTube — ', $document->getTitle());
    }

    public function testNormalizeProviderIdWithCommentId(): void
    {
        $videoUrl = 'https://www.youtube.com/watch?v=abc123';
        $commentId = 'Ugx_specificId789';
        $item = [
            'id' => $commentId,
            'videoUrl' => $videoUrl,
            'text' => 'Testing provider ID generation when the comment has an id field present.',
            'author' => 'User',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $providerId = $document->getProviderId();
        $this->assertNotNull($providerId);
        $this->assertEquals(
            \sprintf('apify:streamers/youtube-comments-scraper:%s:%s', $videoUrl, $commentId),
            $providerId
        );
    }

    public function testNormalizeProviderIdFallbackToDatasetIndex(): void
    {
        // No id field — fallback to datasetId:itemIndex
        $videoUrl = 'https://www.youtube.com/watch?v=abc123';
        $item = [
            'videoUrl' => $videoUrl,
            'text' => 'A comment without any id field — provider ID should use dataset and item index.',
            'author' => 'User',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $providerId = $document->getProviderId();
        $this->assertNotNull($providerId);
        $this->assertEquals(
            \sprintf('apify:streamers/youtube-comments-scraper:%s:%s:%d', $videoUrl, 'dataset-comments-001', 3),
            $providerId
        );
    }

    public function testNormalizeVideoUrlFallback(): void
    {
        // url fallback when videoUrl is absent
        $item = [
            'url' => 'https://www.youtube.com/watch?v=fallback',
            'text' => 'This comment tests the video URL fallback chain from videoUrl to url field.',
            'author' => 'User',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $this->assertEquals('https://www.youtube.com/watch?v=fallback', $document->getUrl());
    }

    public function testNormalizeDateFallback(): void
    {
        $item = [
            'videoUrl' => 'https://www.youtube.com/watch?v=abc123',
            'text' => 'A comment with a createdAt field to verify the date fallback chain.',
            'author' => 'User',
            'createdAt' => '2024-02-10T08:00:00Z',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $this->assertInstanceOf(\DateTimeImmutable::class, $document->getDatePublish());
    }

    public function testNormalizeAuthorNameFallback(): void
    {
        // author absent, authorName used as second fallback
        $item = [
            'videoUrl' => 'https://www.youtube.com/watch?v=abc123',
            'text' => 'A comment using authorName field as fallback when author is absent here.',
            'authorName' => 'AuthorNameFallback',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $this->assertStringContainsString('AuthorNameFallback', $document->getTitle());
    }

    public function testNormalizeUsernameFallback(): void
    {
        // author and authorName absent, username used as last fallback
        $item = [
            'videoUrl' => 'https://www.youtube.com/watch?v=abc123',
            'text' => 'A comment using username field as last fallback when author fields are absent.',
            'username' => 'user_handle',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $this->assertStringContainsString('user_handle', $document->getTitle());
    }

    public function testNormalizeIntegerCommentId(): void
    {
        // Integer ID (e.g., from some actor versions) should be cast to string
        $videoUrl = 'https://www.youtube.com/watch?v=abc123';
        $item = [
            'id' => 99887766,
            'videoUrl' => $videoUrl,
            'text' => 'A comment whose id field is an integer — should still be used in providerId.',
            'author' => 'User',
        ];

        $document = $this->normalizer->normalize($item, $this->context);

        $this->assertNotNull($document);
        $providerId = $document->getProviderId();
        $this->assertNotNull($providerId);
        $this->assertStringContainsString('99887766', $providerId);
        // Should NOT fall back to dataset:index when an integer id is present
        $this->assertStringNotContainsString('dataset-comments-001', $providerId);
    }
}
