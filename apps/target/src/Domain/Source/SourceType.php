<?php

declare(strict_types=1);

namespace App\Domain\Source;

enum SourceType: string
{
    case RSS_FEED = 'rss_feed';
    case BLOG = 'blog';
    case WEBSITE = 'website'; // deep scraping

    // Twitter/X
    case SOCIAL_MEDIA_X_SEARCH = 'social_media:x:search';
    case SOCIAL_MEDIA_X_HASHTAG = 'social_media:x:hashtag';
    case SOCIAL_MEDIA_X_USER = 'social_media:x:user';
    case SOCIAL_MEDIA_TWITTER = 'social_media:twitter';

    // TikTok
    case SOCIAL_MEDIA_TIKTOK = 'social_media:tiktok';

    // Facebook
    case SOCIAL_MEDIA_FACEBOOK_PAGE = 'social_media:facebook:page';
    case SOCIAL_MEDIA_FACEBOOK_GROUP = 'social_media:facebook:group';
    case SOCIAL_MEDIA_FACEBOOK_USER = 'social_media:facebook:user';
    case SOCIAL_MEDIA_FACEBOOK_SEARCH = 'social_media:facebook:search';
    case SOCIAL_MEDIA_FACEBOOK_ADS = 'social_media:facebook:ads';

    // LinkedIn
    case SOCIAL_MEDIA_LINKEDIN_COMPANY = 'social_media:linkedin:company';
    case SOCIAL_MEDIA_LINKEDIN_USER = 'social_media:linkedin:user';
    case SOCIAL_MEDIA_LINKEDIN_GROUP = 'social_media:linkedin:group';
    case SOCIAL_MEDIA_LINKEDIN_SEARCH = 'social_media:linkedin:search';

    // Instagram
    case SOCIAL_MEDIA_INSTAGRAM_USER = 'social_media:instagram:user';
    case SOCIAL_MEDIA_INSTAGRAM_HASHTAG = 'social_media:instagram:hashtag';
    case SOCIAL_MEDIA_INSTAGRAM_SEARCH = 'social_media:instagram:search';

    // Questel
    case DOCUMENT_QUESTEL_SEARCH = 'document:questel:search';

    // ZoneEvents
    case EVENT_GEOCONFIRMED_ZONE = 'event:geoconfirmed:zone';

    // Video Youtube
    case VIDEO_YOUTUBE_COMMENTS = 'video:youtube:comments';
    case VIDEO_YOUTUBE_CHANNEL = 'video:youtube:channel';
    case VIDEO_YOUTUBE_PLAYLIST = 'video:youtube:playlist';
    case VIDEO_YOUTUBE_SEARCH = 'video:youtube:search';

    // Video Odysee
    case VIDEO_ODYSEE = 'video:odysee';

    // Reddit
    case SOCIAL_MEDIA_REDDIT_SUBREDDIT = 'social_media:reddit:subreddit';
    case SOCIAL_MEDIA_REDDIT_USER = 'social_media:reddit:user';

    // Telegram
    case SOCIAL_MEDIA_TELEGRAM_CHANNEL = 'social_media:telegram:channel';

    // Mastodon
    case SOCIAL_MEDIA_MASTODON_USER = 'social_media:mastodon:user';
    case SOCIAL_MEDIA_MASTODON_SEARCH = 'social_media:mastodon:search';

    // Dailymotion
    case VIDEO_DAILYMOTION_USER = 'video:dailymotion:user';
    case VIDEO_DAILYMOTION_SEARCH = 'video:dailymotion:search';

    // Gab
    case SOCIAL_MEDIA_GAB_GROUP = 'social_media:gab:group';
    case SOCIAL_MEDIA_GAB_SEARCH = 'social_media:gab:search';

    // Espacenet
    case DOCUMENT_ESPACENET_ADVANCED_SEARCH = 'document:espacenet:advanced_search';
    case DOCUMENT_ESPACENET_SMART_SEARCH = 'document:espacenet:smart_search';

    // IEEE Xplore
    case DOCUMENT_IEEEXPLORE_PUBLICATIONS = 'document:ieeexplore:publications';

    // NewsAPI
    case NEWS_NEWSAPI = 'news:newsapi';
    case NEWS_NEWSAPI_HEADLINES = 'news:newsapi:headlines';

    // FTP
    case FILE_FTP = 'file:ftp';

    // SerpAPI / Google Images
    case IMAGE_GOOGLE_IMAGES = 'image:google:images';

    // Domains
    case DOMAIN_COMPANY_NAME = 'domain:company_name';
    case DOMAIN_SEARCH = 'domain:search';

    // Autres sources
    case NEWSLETTER = 'newsletter';

    // Manual document creation
    case MANUAL = 'manual';

    public function isSocialMedia(): bool
    {
        return str_starts_with($this->value, 'social_media:');
    }

    /**
     * Whether this source type is internal and should be hidden from UI listings and agent payloads.
     */
    public function isInternal(): bool
    {
        return self::MANUAL === $this;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_values(array_map(
            fn ($case) => $case->value,
            array_filter(self::cases(), fn ($case) => !$case->isInternal()),
        ));
    }
}
