<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\ApifyDocumentNormalizerInterface;
use App\Domain\Collect\NormalizerContext;
use App\Domain\Document\Document;

readonly class GoogleNewsNormalizer implements ApifyDocumentNormalizerInterface
{
    use ApifyNormalizerTrait;
    private const string ACTOR_TYPE = 'lhotanova/google-news-scraper';

    public function supports(string $actorType): bool
    {
        return self::ACTOR_TYPE === $actorType;
    }

    /**
     * @param array<string, mixed> $item
     *
     * Maps: title → title, description → excerpt+content, url → url+providerId,
     *       publishedAt → datePublish
     */
    public function normalize(array $item, NormalizerContext $context): ?Document
    {
        $url = $this->extractString($item, ['url', 'link']);
        if (null === $url || '' === $url) {
            return null;
        }

        $title = $this->buildTitle($item, ['title']);

        $description = $this->extractString($item, ['description', 'snippet']) ?? '';
        $excerpt = $this->buildExcerpt($description, $title);
        if (null === $excerpt) {
            return null;
        }

        $content = '' !== $description ? $description : $title;

        $dateCollect = new \DateTimeImmutable();
        $datePublish = $this->buildDate($item, ['publishedAt', 'date']) ?? $dateCollect;

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

        $document->setProviderId(\sprintf('apify:%s:%s', self::ACTOR_TYPE, $url));

        return $document;
    }
}
