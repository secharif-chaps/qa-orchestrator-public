<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\ApifyDocumentNormalizerInterface;
use App\Domain\Collect\NormalizerContext;
use App\Domain\Document\Document;

readonly class TikTokNormalizer implements ApifyDocumentNormalizerInterface
{
    use ApifyNormalizerTrait;
    private const ACTOR_TYPE = 'clockworks/tiktok-scraper';

    public function supports(string $actorType): bool
    {
        return self::ACTOR_TYPE === $actorType;
    }

    public function normalize(array $item, NormalizerContext $context): ?Document
    {
        // Validate required URL — webVideoUrl is the primary field, with fallbacks
        $videoUrl = $this->extractString($item, ['webVideoUrl', 'videoUrl', 'url', 'link']);
        if (null === $videoUrl || '' === $videoUrl) {
            return null;
        }

        // Validate required description (maps to document.content)
        $desc = $this->extractString($item, ['desc', 'description', 'text']);
        if (null === $desc || '' === $desc) {
            return null;
        }

        // Validate excerpt — returns null if desc is too short (< 10 chars)
        $excerpt = $this->buildExcerpt($desc);
        if (null === $excerpt) {
            return null;
        }

        // Build title: author.nickname + desc[:80]
        $author = \is_array($item['author'] ?? null) ? $item['author'] : [];
        $nickname = $this->extractString($author, ['nickname', 'uniqueId', 'name']) ?? 'TikTok';
        $descExcerpt = mb_substr($desc, 0, 80);
        $title = trim($nickname . ' — ' . $descExcerpt);

        // Dates — createTime is a Unix timestamp (int), handle before ISO string fallbacks
        $dateCollect = new \DateTimeImmutable();
        $datePublish = $this->buildDateWithUnixFallback($item) ?? $dateCollect;

        $document = new Document(
            id: null,
            title: $title,
            excerpt: $excerpt,
            type: 'html',
            datePublish: $datePublish,
            dateCollect: $dateCollect,
            content: $desc,
            url: $videoUrl,
        );

        $document->setProviderId(\sprintf('apify:%s:%s', self::ACTOR_TYPE, $videoUrl));

        return $document;
    }

    /**
     * Build publish date, handling createTime as Unix timestamp (int) before ISO string fallbacks.
     *
     * @param array<string, mixed> $item
     */
    private function buildDateWithUnixFallback(array $item): ?\DateTimeImmutable
    {
        // createTime is a Unix timestamp integer — convert explicitly
        $createTime = $item['createTime'] ?? null;
        if (\is_int($createTime) || (is_numeric($createTime) && !empty($createTime))) {
            $date = \DateTimeImmutable::createFromFormat('U', (string) (int) $createTime);
            if (false !== $date) {
                return $date;
            }
        }

        // Fallback to ISO string fields
        return $this->buildDate($item, ['publishedAt', 'date']);
    }
}
