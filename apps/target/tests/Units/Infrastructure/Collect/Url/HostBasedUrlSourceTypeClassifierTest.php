<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Url;

use App\Domain\Source\SourceType;
use App\Infrastructure\Collect\Url\HostBasedUrlSourceTypeClassifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(HostBasedUrlSourceTypeClassifier::class)]
class HostBasedUrlSourceTypeClassifierTest extends TestCase
{
    private HostBasedUrlSourceTypeClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new HostBasedUrlSourceTypeClassifier();
    }

    /**
     * @return iterable<string, array{0: string, 1: ?SourceType}>
     */
    public static function urlProvider(): iterable
    {
        yield 'twitter user url' => ['https://twitter.com/elonmusk/status/123', SourceType::SOCIAL_MEDIA_TWITTER];
        yield 'x.com renamed twitter' => ['https://x.com/elonmusk', SourceType::SOCIAL_MEDIA_TWITTER];
        yield 'mobile twitter subdomain' => [
            'https://mobile.twitter.com/something',
            SourceType::SOCIAL_MEDIA_TWITTER,
        ];
        yield 'linkedin profile' => ['https://www.linkedin.com/in/jdoe', SourceType::SOCIAL_MEDIA_LINKEDIN_USER];
        yield 'youtube channel' => ['https://www.youtube.com/@channel', SourceType::VIDEO_YOUTUBE_CHANNEL];
        yield 'youtube short link' => ['https://youtu.be/abc', SourceType::VIDEO_YOUTUBE_CHANNEL];
        yield 'tiktok profile' => ['https://www.tiktok.com/@user/video/1', SourceType::SOCIAL_MEDIA_TIKTOK];
        yield 'instagram post' => ['https://www.instagram.com/p/abc', SourceType::SOCIAL_MEDIA_INSTAGRAM_USER];
        yield 'facebook page' => ['https://www.facebook.com/somepage', SourceType::SOCIAL_MEDIA_FACEBOOK_PAGE];
        yield 'reddit post' => [
            'https://www.reddit.com/r/php/comments/xyz',
            SourceType::SOCIAL_MEDIA_REDDIT_SUBREDDIT,
        ];
        yield 'fallback to MANUAL on generic web host' => ['https://example.com/article', SourceType::MANUAL];
        yield 'fallback to MANUAL on news website' => [
            'https://www.lemonde.fr/politique/article/2026/05/06/title.html',
            SourceType::MANUAL,
        ];
    }

    #[DataProvider('urlProvider')]
    public function testClassifiesKnownPlatformsAndFallsBackToManual(string $url, SourceType $expected): void
    {
        self::assertSame($expected, $this->classifier->classify($url));
    }

    public function testReturnsNullOnMalformedUrl(): void
    {
        self::assertNull($this->classifier->classify('not a url at all'));
    }

    public function testReturnsNullOnUrlWithoutHost(): void
    {
        // parse_url accepts `mailto:` and similar schemes that have no host.
        self::assertNull($this->classifier->classify('mailto:test@example.com'));
    }

    public function testIsCaseInsensitiveOnHost(): void
    {
        self::assertSame(
            SourceType::SOCIAL_MEDIA_TWITTER,
            $this->classifier->classify('https://TWITTER.COM/elonmusk'),
        );
    }
}
