<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\ApifyDocumentNormalizerInterface;
use App\Domain\Collect\NormalizerContext;
use App\Domain\Document\Document;

readonly class FacebookPostNormalizer implements ApifyDocumentNormalizerInterface
{
    use ApifyNormalizerTrait;
    private const ACTOR_TYPE = 'apify/facebook-posts-scraper';

    public function supports(string $actorType): bool
    {
        return self::ACTOR_TYPE === $actorType;
    }

    public function normalize(array $item, NormalizerContext $context): ?Document
    {
        // Extract post text/content (required)
        $text = $this->extractString($item, ['text', 'content', 'description', 'message']);
        if (null === $text || '' === $text) {
            return null;
        }

        // Extract URL (required for deduplication)
        $postUrl = $this->extractString($item, ['url', 'link', 'postUrl', 'permalink']);
        if (null === $postUrl || '' === $postUrl) {
            return null;
        }

        // Extract page/author name
        $pageName = $this->extractString($item, ['pageName', 'page_name', 'author', 'authorName']) ?? 'Facebook Post';

        // Build title: page name + first 80 chars of text
        $textExcerpt = mb_substr($text, 0, 80);
        $title = trim($pageName . ' — ' . $textExcerpt);

        // Validate excerpt
        $excerpt = $this->buildExcerpt($text);
        if (null === $excerpt) {
            return null;
        }

        // Dates
        $dateCollect = new \DateTimeImmutable();
        $datePublish = $this->buildDate(
            $item,
            ['time', 'timestamp', 'createdTime', 'created_time', 'publishedAt']
        ) ?? $dateCollect;

        // Document construction
        $document = new Document(
            id: null,
            title: $title,
            excerpt: $excerpt,
            type: 'html',
            datePublish: $datePublish,
            dateCollect: $dateCollect,
            content: $text,
            url: $postUrl,
        );

        $document->setProviderId(\sprintf('apify:%s:%s', self::ACTOR_TYPE, $postUrl));

        $likes = $this->extractInt($item, ['likes', 'likesCount', 'reaction_count']);
        $comments = $this->extractInt($item, ['comments', 'commentsCount', 'comment_count']);
        $shares = $this->extractInt($item, ['shares', 'sharesCount', 'share_count']);
        if (null !== $likes || null !== $comments || null !== $shares) {
            $document->setMetadata([
                'engagement' => [
                    'likes' => $likes ?? 0,
                    'comments' => $comments ?? 0,
                    'shares' => $shares ?? 0,
                ],
            ]);
        }

        return $document;
    }
}
