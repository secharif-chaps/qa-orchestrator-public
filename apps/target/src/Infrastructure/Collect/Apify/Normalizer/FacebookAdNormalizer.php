<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\ApifyDocumentNormalizerInterface;
use App\Domain\Collect\NormalizerContext;
use App\Domain\Document\Document;

readonly class FacebookAdNormalizer implements ApifyDocumentNormalizerInterface
{
    use ApifyNormalizerTrait;
    private const ACTOR_TYPE = 'apify/facebook-ads-scraper';

    public function supports(string $actorType): bool
    {
        return self::ACTOR_TYPE === $actorType;
    }

    public function normalize(array $item, NormalizerContext $context): ?Document
    {
        // Extract ad creative body/text (required)
        $adText = $this->extractString($item, ['ad_creative_body', 'creative_body', 'text', 'message', 'body']);
        if (null === $adText || '' === $adText) {
            return null;
        }

        // Extract ad URL (required for deduplication)
        $adUrl = $this->extractString($item, ['ad_snapshot_url', 'snapshot_url', 'url', 'link']);
        if (null === $adUrl || '' === $adUrl) {
            return null;
        }

        // Extract publisher/page name
        $publisherName = $this->extractString(
            $item,
            ['page_name', 'publisher_name', 'advertiser_name', 'advertiser']
        ) ?? 'Facebook Ad';

        // Build title: page name + first 80 chars of text
        $textExcerpt = mb_substr($adText, 0, 80);
        $title = trim($publisherName . ' — ' . $textExcerpt);

        // Validate excerpt
        $excerpt = $this->buildExcerpt($adText);
        if (null === $excerpt) {
            return null;
        }

        // Dates
        $dateCollect = new \DateTimeImmutable();
        $datePublish = $this->buildDate(
            $item,
            ['ad_delivery_start_time', 'start_date', 'created_at', 'timestamp']
        ) ?? $dateCollect;

        // Document construction
        $document = new Document(
            id: null,
            title: $title,
            excerpt: $excerpt,
            type: 'html',
            datePublish: $datePublish,
            dateCollect: $dateCollect,
            content: $adText,
            url: $adUrl,
        );

        $document->setProviderId(\sprintf('apify:%s:%s', self::ACTOR_TYPE, $adUrl));

        $metadata = [];
        $publisherPlatforms = $this->extractStringOrArray($item, ['publisher_platforms', 'platforms']);
        if (null !== $publisherPlatforms) {
            $metadata['publisher_platforms'] = $publisherPlatforms;
        }
        $impressions = $this->extractInt($item, ['impressions', 'num_impressions']);
        if (null !== $impressions) {
            $metadata['impressions'] = $impressions;
        }
        if ([] !== $metadata) {
            $document->setMetadata($metadata);
        }

        return $document;
    }
}
