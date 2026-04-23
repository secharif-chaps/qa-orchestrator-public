<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\ApifyDocumentNormalizerInterface;
use App\Domain\Collect\NormalizerContext;
use App\Domain\Document\Document;

readonly class WebsiteCrawlerNormalizer implements ApifyDocumentNormalizerInterface
{
    use ApifyNormalizerTrait;
    private const string ACTOR_TYPE = 'apify/website-content-crawler';
    private const int MIN_HTTP_STATUS = 200;
    private const int MAX_HTTP_STATUS = 299;

    public function supports(string $actorType): bool
    {
        return self::ACTOR_TYPE === $actorType;
    }

    /**
     * @param array<string, mixed> $item
     *
     * Maps: title → title, text/markdown → content, url → url+providerId,
     *       crawl.loadedAt → dateCollect, crawl.httpStatusCode → validation
     */
    public function normalize(array $item, NormalizerContext $context): ?Document
    {
        $url = $this->extractString($item, ['url', 'loadedUrl', 'canonicalUrl']);
        if (null === $url || '' === $url) {
            return null;
        }

        // Validate HTTP status — skip error pages
        $crawl = \is_array($item['crawl'] ?? null) ? $item['crawl'] : [];
        $httpStatus = $crawl['httpStatusCode'] ?? null;
        if (!\is_int($httpStatus) || $httpStatus < self::MIN_HTTP_STATUS || $httpStatus > self::MAX_HTTP_STATUS) {
            return null;
        }

        $title = $this->buildTitle($item, ['title']);

        // Prefer markdown over plain text for richer content
        $content = $this->extractString($item, ['markdown', 'text', 'html']) ?? '';
        if ('' === $content) {
            return null;
        }

        $excerpt = $this->buildExcerpt($content, $title);
        if (null === $excerpt) {
            return null;
        }

        $dateCollect = $this->parseDate($crawl['loadedAt'] ?? null) ?? new \DateTimeImmutable();

        $document = new Document(
            id: null,
            title: $title,
            excerpt: $excerpt,
            type: 'html',
            datePublish: $dateCollect,
            dateCollect: $dateCollect,
            content: $content,
            url: $url,
        );

        $document->setProviderId(\sprintf('apify:%s:%s', self::ACTOR_TYPE, $url));

        return $document;
    }
}
