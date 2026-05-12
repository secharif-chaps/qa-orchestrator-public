<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\ApifyDocumentNormalizerInterface;
use App\Domain\Collect\NormalizerContext;
use App\Domain\Document\Document;

readonly class YouTubeCommentNormalizer implements ApifyDocumentNormalizerInterface
{
    use ApifyNormalizerTrait;
    private const ACTOR_TYPE = 'streamers/youtube-comments-scraper';

    public function supports(string $actorType): bool
    {
        return self::ACTOR_TYPE === $actorType;
    }

    public function normalize(array $item, NormalizerContext $context): ?Document
    {
        $videoUrl = $this->extractString($item, ['videoUrl', 'url', 'link']);
        if (null === $videoUrl || '' === $videoUrl) {
            return null;
        }

        $text = $this->extractString($item, ['text', 'content', 'comment']);
        if (null === $text || '' === $text) {
            return null;
        }

        $excerpt = $this->buildExcerpt($text);
        if (null === $excerpt) {
            return null;
        }

        $author = $this->extractString($item, ['author', 'authorName', 'username']) ?? 'YouTube';
        $title = trim($author . ' — ' . mb_substr($text, 0, 80));

        $dateCollect = new \DateTimeImmutable();
        $datePublish = $this->buildDate($item, ['publishedAt', 'createdAt', 'date']) ?? $dateCollect;

        $document = new Document(
            id: null,
            title: $title,
            excerpt: $excerpt,
            type: 'html',
            datePublish: $datePublish,
            dateCollect: $dateCollect,
            content: $text,
            url: $videoUrl,
        );

        // Extract ID, handling both string and integer types
        $commentId = null;
        $rawId = $item['id'] ?? $item['commentId'] ?? null;
        if (\is_string($rawId) && '' !== $rawId) {
            $commentId = $rawId;
        } elseif (\is_int($rawId)) {
            $commentId = (string) $rawId;
        }
        $providerId = null !== $commentId
            ? \sprintf('apify:%s:%s:%s', self::ACTOR_TYPE, $videoUrl, $commentId)
            : \sprintf('apify:%s:%s:%s:%d', self::ACTOR_TYPE, $videoUrl, $context->datasetId, $context->itemIndex);

        $document->setProviderId($providerId);

        return $document;
    }
}
