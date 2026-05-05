<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\ApifyDocumentNormalizerInterface;
use App\Domain\Collect\NormalizerContext;
use App\Domain\Document\Document;

readonly class TwitterTweetNormalizer implements ApifyDocumentNormalizerInterface
{
    use ApifyNormalizerTrait;
    private const ACTOR_TYPE = 'apidojo/tweet-scraper';

    public function supports(string $actorType): bool
    {
        return self::ACTOR_TYPE === $actorType;
    }

    public function normalize(array $item, NormalizerContext $context): ?Document
    {
        // Validate required fields — actor returns both url (x.com) and twitterUrl (twitter.com)
        $tweetUrl = $this->extractString($item, ['twitterUrl', 'url', 'link']);
        if (null === $tweetUrl || '' === $tweetUrl) {
            return null;
        }

        // Extract tweet text (required)
        $text = $this->extractString($item, ['fullText', 'text', 'content', 'description']);
        if (null === $text || '' === $text) {
            return null;
        }

        // Actor v2 uses "author" object; legacy format used "user"
        $author = \is_array($item['author'] ?? null) ? $item['author'] : (\is_array(
            $item['user'] ?? null
        ) ? $item['user'] : []);
        $authorName = $this->extractString($author, ['name', 'fullName']) ?? 'Tweet';

        $textExcerpt = mb_substr($text, 0, 80);
        $title = trim($authorName . ' — ' . $textExcerpt);

        // Validate excerpt
        $excerpt = $this->buildExcerpt($text);
        if (null === $excerpt) {
            return null;
        }

        // Dates
        $dateCollect = new \DateTimeImmutable();
        $datePublish = $this->buildDate($item, ['createdAt', 'publishedAt', 'date']) ?? $dateCollect;

        // Document construction
        $document = new Document(
            id: null,
            title: $title,
            excerpt: $excerpt,
            type: 'html',
            datePublish: $datePublish,
            dateCollect: $dateCollect,
            content: $text,
            url: $tweetUrl,
        );

        $document->setProviderId(\sprintf('apify:%s:%s', self::ACTOR_TYPE, $tweetUrl));

        return $document;
    }
}
