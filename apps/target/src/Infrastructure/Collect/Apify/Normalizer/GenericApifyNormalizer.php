<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\ApifyFallbackNormalizerInterface;
use App\Domain\Collect\NormalizerContext;
use App\Domain\Document\Document;

/**
 * Fallback normalizer for Apify actors without a dedicated implementation.
 * Tries common field names across all actor output schemas.
 */
readonly class GenericApifyNormalizer implements ApifyFallbackNormalizerInterface
{
    use ApifyNormalizerTrait;
    private const string ACTOR_TYPE_PREFIX = 'generic';

    public function supports(string $actorType): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $item
     */
    public function normalize(array $item, NormalizerContext $context): ?Document
    {
        $url = $this->extractString($item, ['url', 'link', 'profileUrl', 'pageUrl', 'canonicalUrl']);

        $title = $this->buildTitle($item, ['title', 'name', 'headline', 'subject']);

        $content = $this->extractString(
            $item,
            ['text', 'content', 'description', 'markdown', 'body', 'about']
        ) ?? $title;

        $excerpt = $this->buildExcerpt($content, $title);
        if (null === $excerpt) {
            return null;
        }

        $dateCollect = new \DateTimeImmutable();
        $datePublish = $this->buildDate($item, ['publishedAt', 'date', 'createdAt']) ?? $dateCollect;

        $document = new Document(
            id: null,
            title: $title,
            excerpt: $excerpt,
            type: 'html',
            datePublish: $datePublish,
            dateCollect: $dateCollect,
            content: $content,
            url: $url,
        );

        $actorPrefix = $context->actorType ?? self::ACTOR_TYPE_PREFIX;
        $providerId = null !== $url && '' !== $url
            ? \sprintf('apify:%s:%s', $actorPrefix, $url)
            : \sprintf('apify:%s:%s:%d', $actorPrefix, $context->datasetId, $context->itemIndex);

        $document->setProviderId($providerId);

        return $document;
    }
}
