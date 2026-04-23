<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\ApifyDocumentNormalizerInterface;
use App\Domain\Collect\NormalizerContext;
use App\Domain\Document\Document;
use App\Domain\Document\HtmlMetadata;

readonly class LinkedInProfileNormalizer implements ApifyDocumentNormalizerInterface
{
    use ApifyNormalizerTrait;
    private const string ACTOR_TYPE = 'curious_coder/linkedin-profile-scraper';

    public function supports(string $actorType): bool
    {
        return self::ACTOR_TYPE === $actorType;
    }

    /**
     * @param array<string, mixed> $item
     *
     * Maps: name+headline → title, about → content, profileUrl → url+providerId,
     *       experience → appended to content
     */
    public function normalize(array $item, NormalizerContext $context): ?Document
    {
        $url = $this->extractString($item, ['profileUrl', 'url', 'linkedInUrl']);
        if (null === $url || '' === $url) {
            return null;
        }

        $name = $this->extractString($item, ['name', 'fullName']) ?? '';
        $headline = $this->extractString($item, ['headline', 'title']) ?? '';

        $titleRaw = '' !== $name && '' !== $headline ? $name . ' - ' . $headline : ($name ?: $headline);
        $title = '' !== $titleRaw ? mb_substr($titleRaw, 0, 500) : HtmlMetadata::UNTITLED;

        $about = $this->extractString($item, ['about', 'summary', 'description']) ?? '';
        $content = $this->buildContent($item, $about);

        $excerpt = $this->buildExcerpt($about, $content, $title);
        if (null === $excerpt) {
            return null;
        }

        $dateCollect = new \DateTimeImmutable();
        $datePublish = $this->buildDate($item, ['publishedAt', 'createdAt']) ?? $dateCollect;

        $document = new Document(
            id: null,
            title: $title,
            excerpt: $excerpt,
            type: 'html',
            datePublish: $datePublish,
            dateCollect: $dateCollect,
            content: $content ?: $title,
            url: $url,
        );

        $document->setProviderId(\sprintf('apify:%s:%s', self::ACTOR_TYPE, $url));

        return $document;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function buildContent(array $item, string $about): string
    {
        $parts = [];

        if ('' !== $about) {
            $parts[] = $about;
        }

        $experience = $item['experience'] ?? null;
        if (\is_array($experience) && [] !== $experience) {
            $lines = [];
            foreach ($experience as $exp) {
                if (!\is_array($exp)) {
                    continue;
                }
                $title = \is_string($exp['title'] ?? null) ? $exp['title'] : '';
                $company = \is_string($exp['company'] ?? null) ? $exp['company'] : '';
                if ('' !== $title || '' !== $company) {
                    $lines[] = trim($title . ' @ ' . $company, ' @');
                }
            }
            if ([] !== $lines) {
                $parts[] = 'Experience: ' . implode(', ', $lines);
            }
        }

        return implode("\n\n", $parts);
    }
}
