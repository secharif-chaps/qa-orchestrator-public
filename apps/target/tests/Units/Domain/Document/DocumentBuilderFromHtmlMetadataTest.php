<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document;

use App\Domain\Document\DocumentBuilderFromHtmlMetadata;
use App\Domain\Document\HtmlMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentBuilderFromHtmlMetadata::class)]
class DocumentBuilderFromHtmlMetadataTest extends TestCase
{
    private DocumentBuilderFromHtmlMetadata $builder;

    protected function setUp(): void
    {
        $this->builder = new DocumentBuilderFromHtmlMetadata();
    }

    public function testUsesMetadataTitleAndExcerptByDefault(): void
    {
        $metadata = $this->makeMetadata(title: 'Article from metadata', excerpt: 'Excerpt from metadata');

        $document = $this->builder->build(
            metadata: $metadata,
            rawHtml: '<html>…</html>',
            sourceUrl: 'https://example.com/article',
        );

        self::assertSame('Article from metadata', $document->getTitle());
        self::assertSame('Excerpt from metadata', $document->getExcerpt());
    }

    public function testTitleAndExcerptOverridesReplaceMetadataValues(): void
    {
        $metadata = $this->makeMetadata(title: 'Original', excerpt: 'Original excerpt');

        $document = $this->builder->build(
            metadata: $metadata,
            rawHtml: '<html>…</html>',
            sourceUrl: 'https://example.com/article',
            titleOverride: 'User-provided title',
            excerptOverride: 'User-provided excerpt',
        );

        self::assertSame('User-provided title', $document->getTitle());
        self::assertSame('User-provided excerpt', $document->getExcerpt());
    }

    public function testContentTypeAndLanguageArePropagated(): void
    {
        $metadata = $this->makeMetadata(content: '<p>body</p>', language: 'fr');

        $document = $this->builder->build($metadata, '<html>…</html>', null);

        self::assertSame('<p>body</p>', $document->getContent());
        self::assertSame('html', $document->getType());
        self::assertSame('fr', $document->getLanguage());
    }

    public function testDatePublishFromMetadataIsKept(): void
    {
        $metaDate = new \DateTimeImmutable('2024-06-15T12:00:00Z');
        $metadata = $this->makeMetadata(datePublish: $metaDate);

        $document = $this->builder->build($metadata, '<html>…</html>', null);

        self::assertEquals($metaDate, $document->getDatePublish());
    }

    public function testDatePublishFallsBackToNowWhenAbsentFromMetadata(): void
    {
        $metadata = $this->makeMetadata(datePublish: null);

        $before = new \DateTimeImmutable();
        $document = $this->builder->build($metadata, '<html>…</html>', null);
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $document->getDatePublish());
        self::assertLessThanOrEqual($after, $document->getDatePublish());
    }

    public function testCanonicalUrlIsPreferredOverSourceUrlForDocumentUrl(): void
    {
        $metadata = $this->makeMetadata(canonicalUrl: 'https://canonical.example.com/article');

        $document = $this->builder->build(
            metadata: $metadata,
            rawHtml: '<html>…</html>',
            sourceUrl: 'https://source.example.com/article?utm_source=x',
        );

        self::assertSame('https://canonical.example.com/article', $document->getUrl());
    }

    public function testFallsBackToSourceUrlWhenCanonicalIsAbsent(): void
    {
        $metadata = $this->makeMetadata(canonicalUrl: null);

        $document = $this->builder->build(
            metadata: $metadata,
            rawHtml: '<html>…</html>',
            sourceUrl: 'https://source.example.com/article',
        );

        self::assertSame('https://source.example.com/article', $document->getUrl());
    }

    public function testUrlIsNullWhenNeitherCanonicalNorSourceProvided(): void
    {
        $metadata = $this->makeMetadata(canonicalUrl: null);

        $document = $this->builder->build($metadata, '<html>…</html>', null);

        self::assertNull($document->getUrl());
    }

    public function testContentRatioIsLengthOfContentOverLengthOfRawHtml(): void
    {
        $metadata = $this->makeMetadata(content: str_repeat('a', 250));
        $rawHtml = str_repeat('x', 1000);

        $document = $this->builder->build($metadata, $rawHtml, null);

        self::assertSame(0.25, $document->getContentRatio());
    }

    public function testContentRatioIsNullWhenRawHtmlIsEmpty(): void
    {
        $metadata = $this->makeMetadata(content: '<p>body</p>');

        $document = $this->builder->build($metadata, '', null);

        self::assertNull($document->getContentRatio());
    }

    public function testProviderIdIsSha256OfCanonicalUrlWhenPresent(): void
    {
        $metadata = $this->makeMetadata(canonicalUrl: 'https://canonical.example.com/article');

        $document = $this->builder->build(
            metadata: $metadata,
            rawHtml: '<html>…</html>',
            sourceUrl: 'https://source.example.com/article?utm_source=x',
        );

        self::assertSame(hash('sha256', 'https://canonical.example.com/article'), $document->getProviderId());
    }

    public function testProviderIdFallsBackToSha256OfSourceUrlWhenCanonicalAbsent(): void
    {
        $metadata = $this->makeMetadata(canonicalUrl: null);

        $document = $this->builder->build(
            metadata: $metadata,
            rawHtml: '<html>…</html>',
            sourceUrl: 'https://source.example.com/article',
        );

        self::assertSame(hash('sha256', 'https://source.example.com/article'), $document->getProviderId());
    }

    public function testProviderIdHashesFirstTenKBytesOfRawHtmlAsLastResort(): void
    {
        $metadata = $this->makeMetadata(canonicalUrl: null);

        // 12k bytes — only the first 10k must be hashed.
        $rawHtml = str_repeat('x', 12000);

        $document = $this->builder->build($metadata, $rawHtml, null);

        self::assertSame(hash('sha256', str_repeat('x', 10000)), $document->getProviderId());
    }

    public function testTwoBuildsWithSameCanonicalUrlProduceSameProviderId(): void
    {
        // Two providers serving the same syndicated article (different source
        // URLs but same canonical) must collide on providerId — that is the
        // dedup key.
        $metadata = $this->makeMetadata(canonicalUrl: 'https://www.afp.com/article/world');

        $a = $this->builder->build($metadata, '<html>a</html>', 'https://lemonde.fr/republished');
        $b = $this->builder->build($metadata, '<html>b</html>', 'https://lefigaro.fr/republished');

        self::assertSame($a->getProviderId(), $b->getProviderId());
    }

    public function testBuiltDocumentHasFreshIdAndDateCollect(): void
    {
        $metadata = $this->makeMetadata();

        $before = new \DateTimeImmutable();
        $document = $this->builder->build($metadata, '<html>…</html>', null);
        $after = new \DateTimeImmutable();

        self::assertNotEmpty($document->getId());
        self::assertGreaterThanOrEqual($before, $document->getDateCollect());
        self::assertLessThanOrEqual($after, $document->getDateCollect());
    }

    public function testBuiltDocumentIsNotCfcRestrictedByDefault(): void
    {
        $metadata = $this->makeMetadata();

        $document = $this->builder->build($metadata, '<html>…</html>', null);

        self::assertFalse($document->isCfcRestricted());
    }

    private function makeMetadata(
        string $title = 'Default title',
        string $excerpt = 'Default excerpt for the article body',
        string $content = '<p>Default content</p>',
        string $language = 'en',
        ?\DateTimeImmutable $datePublish = null,
        ?string $canonicalUrl = null,
    ): HtmlMetadata {
        return new HtmlMetadata(
            title: $title,
            excerpt: $excerpt,
            content: $content,
            language: $language,
            datePublish: $datePublish,
            imageUrl: null,
            canonicalUrl: $canonicalUrl,
            author: null,
            siteName: null,
        );
    }
}
