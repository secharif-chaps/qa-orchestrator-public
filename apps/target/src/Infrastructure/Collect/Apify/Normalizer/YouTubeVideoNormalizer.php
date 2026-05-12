<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\ApifyDocumentNormalizerInterface;
use App\Domain\Collect\NormalizerContext;
use App\Domain\Document\Document;

readonly class YouTubeVideoNormalizer implements ApifyDocumentNormalizerInterface
{
    use ApifyNormalizerTrait;
    private const ACTOR_TYPE = 'streamers/youtube-scraper';

    public function supports(string $actorType): bool
    {
        return self::ACTOR_TYPE === $actorType;
    }

    public function normalize(array $item, NormalizerContext $context): ?Document
    {
        $url = $this->extractString($item, ['url', 'videoUrl', 'link']);
        if (null === $url || '' === $url) {
            return null;
        }

        $description = $this->extractString($item, ['description', 'text', 'content']);
        if (null === $description || '' === $description) {
            return null;
        }

        $excerpt = $this->buildExcerpt($description);
        if (null === $excerpt) {
            return null;
        }

        $channelName = $this->extractString($item, ['channelName', 'channel', 'authorName']);
        $videoTitle = mb_substr($this->extractString($item, ['title', 'name']) ?? $description, 0, 80);
        $title = null !== $channelName
            ? trim($channelName . ' — ' . $videoTitle)
            : $videoTitle;

        $dateCollect = new \DateTimeImmutable();
        $datePublish = $this->buildDate($item, ['uploadDate', 'publishedAt', 'date']) ?? $dateCollect;

        $document = new Document(
            id: null,
            title: $title,
            excerpt: $excerpt,
            type: 'html',
            datePublish: $datePublish,
            dateCollect: $dateCollect,
            content: $description,
            url: $url,
        );

        $document->setProviderId(\sprintf('apify:%s:%s', self::ACTOR_TYPE, $url));

        return $document;
    }
}
