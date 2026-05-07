<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Url;

use App\Domain\Collect\Url\UrlSourceTypeClassifierInterface;
use App\Domain\Source\SourceType;

/**
 * Host-prefix based URL classifier.
 *
 * The check is intentionally conservative: we only flag a URL as belonging
 * to a structured provider when the host (after stripping `www.`) is one
 * we know about. Everything else falls back to {@see SourceType::MANUAL},
 * which is what the inline `web` provider can handle.
 *
 * The mapping does NOT try to discriminate user vs hashtag vs search on
 * each platform — phase 1 only needs to discriminate "social/video known"
 * vs "MANUAL fallback" so the CLI/API can reject one-shot social URLs
 * with a clear error message. The fine-grained sub-types
 * (`SOCIAL_MEDIA_X_USER` vs `SOCIAL_MEDIA_X_SEARCH` etc.) come into play
 * once dedicated one-shot Apify actors land in phase 2.
 *
 * Each platform is mapped to the most generic SourceType available so
 * the operator-facing error message stays meaningful even if the platform
 * has multiple variants.
 */
readonly class HostBasedUrlSourceTypeClassifier implements UrlSourceTypeClassifierInterface
{
    /**
     * Host suffix → SourceType. The suffix MUST start with a dot OR equal
     * the bare host (so `x.com` matches `x.com` but not `prox.com`). Lookup
     * is done by checking either equality or `endsWith('.suffix')` so
     * subdomains like `mobile.twitter.com` map correctly.
     */
    private const array HOST_TO_SOURCE_TYPE = [
        'twitter.com' => SourceType::SOCIAL_MEDIA_TWITTER,
        'x.com' => SourceType::SOCIAL_MEDIA_TWITTER,
        'linkedin.com' => SourceType::SOCIAL_MEDIA_LINKEDIN_USER,
        'youtube.com' => SourceType::VIDEO_YOUTUBE_CHANNEL,
        'youtu.be' => SourceType::VIDEO_YOUTUBE_CHANNEL,
        'tiktok.com' => SourceType::SOCIAL_MEDIA_TIKTOK,
        'instagram.com' => SourceType::SOCIAL_MEDIA_INSTAGRAM_USER,
        'facebook.com' => SourceType::SOCIAL_MEDIA_FACEBOOK_PAGE,
        'fb.com' => SourceType::SOCIAL_MEDIA_FACEBOOK_PAGE,
        'reddit.com' => SourceType::SOCIAL_MEDIA_REDDIT_SUBREDDIT,
        'redd.it' => SourceType::SOCIAL_MEDIA_REDDIT_SUBREDDIT,
        't.me' => SourceType::SOCIAL_MEDIA_TELEGRAM_CHANNEL,
        'mastodon.social' => SourceType::SOCIAL_MEDIA_MASTODON_USER,
        'odysee.com' => SourceType::VIDEO_ODYSEE,
        'dailymotion.com' => SourceType::VIDEO_DAILYMOTION_USER,
        'gab.com' => SourceType::SOCIAL_MEDIA_GAB_GROUP,
    ];

    public function classify(string $url): ?SourceType
    {
        $parts = parse_url($url);
        if (!\is_array($parts)) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if (!\is_string($host) || '' === $host) {
            return null;
        }

        $host = mb_strtolower($host);
        // Strip the optional `www.` prefix so subdomain-aware matching below
        // still hits — `www.twitter.com` should resolve like `twitter.com`.
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        foreach (self::HOST_TO_SOURCE_TYPE as $suffix => $sourceType) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                return $sourceType;
            }
        }

        return SourceType::MANUAL;
    }
}
